// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Secure Integrity Quiz attempt client.
 *
 * @module     mod_integrityquiz/secure
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as ajaxCall} from 'core/ajax';

/**
 * Call one Moodle external function and return its promise result.
 *
 * @param {String} methodname External function name.
 * @param {Object} args Function arguments.
 * @returns {Promise<Object>}
 */
const request = async(methodname, args) => {
    const requests = ajaxCall([{methodname, args}]);
    return requests[0];
};

/**
 * Initialise the secure attempt UI.
 *
 * @param {Object} cfg Attempt configuration.
 * @returns {void}
 */
export const init = (cfg) => {
    const video = document.getElementById('integrity-camera');
    const status = document.getElementById('integrity-status');
    const cameraState = document.getElementById('integrity-camera-state');
    const enter = document.getElementById('integrity-enter');
    const questionsContainer = document.getElementById('integrity-questions');
    const attemptApp = document.querySelector('.integrity-attempt-app');
    const questionNavContainer = document.getElementById('integrity-question-nav');
    const questionCards = [...document.querySelectorAll('.integrity-question')];
    const timer = document.getElementById('integrity-timer');
    const previous = document.getElementById('integrity-prev');
    const next = document.getElementById('integrity-next');
    const submit = document.getElementById('integrity-submit');
    const pageStatus = document.getElementById('integrity-page-status');
    const progressText = document.getElementById('integrity-progress-text');
    const progressBar = document.getElementById('integrity-progress-bar');
    const questionNav = [...document.querySelectorAll('.iq-question-nav__item')];

    let stream = null;
    let secureStarted = false;
    let suspended = false;
    let finishing = false;
    let questionTimerInterval = null;
    let questionTimerToken = 0;
    const expiredQuestions = new Set();
    const persistedAnswers = new Set();

    const perPage = Number.isInteger(cfg.questionsperpage)
        ? cfg.questionsperpage
        : parseInt(cfg.questionsperpage || 1, 10);
    const questionTimeLimit = parseInt(cfg.questiontimelimit || 0, 10);
    const pageCount = perPage > 0 ? Math.max(1, Math.ceil(questionCards.length / perPage)) : 1;
    const pageStorageKey = 'integrityquiz_questionpage_' + cfg.attemptid;
    let currentPage = Math.min(
        Math.max(parseInt(sessionStorage.getItem(pageStorageKey) || '0', 10), 0),
        pageCount - 1
    );

    let pageid = sessionStorage.getItem('integrityquiz_pageid_' + cfg.attemptid);
    if (!pageid) {
        pageid = Math.random().toString(36).slice(2) + Date.now().toString(36);
        sessionStorage.setItem('integrityquiz_pageid_' + cfg.attemptid, pageid);
    }

    const common = {attemptid: cfg.attemptid};


    // Lock only the active assessment into the available browser viewport.
    // The pre-check remains a normal Moodle page. Once the learner begins,
    // question changes happen in-place without vertical page scrolling.
    const syncAttemptViewportHeight = () => {
        if (!attemptApp || !attemptApp.classList.contains('is-running')) {
            return;
        }
        const rect = attemptApp.getBoundingClientRect();
        const top = Math.max(0, rect.top);
        const available = Math.max(420, window.innerHeight - top - 8);
        attemptApp.style.setProperty('--iq-attempt-height', available + 'px');
    };

    const lockAttemptViewport = () => {
        if (!attemptApp) {
            return;
        }
        const fixedNavbar = document.querySelector('.navbar.fixed-top');
        const navbarOffset = fixedNavbar ? fixedNavbar.getBoundingClientRect().height + 8 : 8;
        const targetTop = Math.max(
            0,
            window.scrollY + attemptApp.getBoundingClientRect().top - navbarOffset
        );
        window.scrollTo({top: targetTop, behavior: 'auto'});

        attemptApp.classList.add('is-running');
        document.documentElement.classList.add('iq-attempt-viewport-locked');
        document.body.classList.add('iq-attempt-viewport-locked');
        syncAttemptViewportHeight();
    };

    const unlockAttemptViewport = () => {
        if (attemptApp) {
            attemptApp.classList.remove('is-running');
            attemptApp.style.removeProperty('--iq-attempt-height');
        }
        document.documentElement.classList.remove('iq-attempt-viewport-locked');
        document.body.classList.remove('iq-attempt-viewport-locked');
    };

    const centerQuestionNavItem = item => {
        if (!item || !questionNavContainer) {
            return;
        }
        const target = item.offsetLeft - ((questionNavContainer.clientWidth - item.offsetWidth) / 2);
        questionNavContainer.scrollTo({
            left: Math.max(0, target),
            behavior: 'smooth',
        });
    };

    const setCameraState = (text, stateClass) => {
        if (!cameraState) {
            return;
        }
        cameraState.textContent = text;
        cameraState.classList.remove('is-active', 'is-optional', 'is-required', 'is-error');
        if (stateClass) {
            cameraState.classList.add(stateClass);
        }
    };

    const stopCamera = () => {
        secureStarted = false;
        if (stream) {
            stream.getTracks().forEach(track => {
                try {
                    track.stop();
                } catch (e) {
                    // Ignore browser-specific track shutdown errors.
                }
            });
            stream = null;
        }
        if (video) {
            video.srcObject = null;
        }
    };

    const suspendIfNeeded = data => {
        if (data && data.state === 'suspended') {
            suspended = true;
            secureStarted = false;
            window.location.reload();
        }
    };

    const event = async(type, detail = '') => {
        if (finishing) {
            return;
        }
        try {
            const data = await request('mod_integrityquiz_log_event', {
                ...common,
                type,
                detail,
                clienttime: Date.now(),
            });
            suspendIfNeeded(data);
        } catch (e) {
            // A later heartbeat will reconcile the server state.
        }
    };

    const snapshot = async kind => {
        if (!stream || !video.videoWidth || suspended || finishing) {
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = Math.min(video.videoWidth, 640);
        canvas.height = Math.round(canvas.width * video.videoHeight / video.videoWidth);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
        const comma = dataUrl.indexOf(',');
        if (comma === -1) {
            return;
        }
        try {
            await request('mod_integrityquiz_save_snapshot', {
                attemptid: cfg.attemptid,
                kind,
                data: dataUrl.slice(comma + 1),
            });
        } catch (e) {
            // Snapshot upload failures must not expose browser internals to the student.
        }
    };

    const scheduleSnapshot = () => {
        const min = Math.max(10, cfg.snapshotmin || 60);
        const max = Math.max(min, cfg.snapshotmax || 120);
        const delay = (min + Math.random() * (max - min)) * 1000;
        window.setTimeout(async() => {
            if (!finishing && !suspended) {
                await snapshot('random');
                scheduleSnapshot();
            }
        }, delay);
    };

    const startCamera = async() => {
        if (!cfg.requirecamera) {
            status.textContent = cfg.cameraoptional;
            setCameraState(cfg.cameraoptional, 'is-optional');
            return true;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: true, audio: false});
            video.srcObject = stream;
            stream.getVideoTracks().forEach(track => track.addEventListener('ended', () => {
                setCameraState(cfg.camerarequired, 'is-error');
                if (secureStarted && !finishing) {
                    event('cameraended', 'Camera stream ended');
                }
            }));
            status.textContent = cfg.cameraactive;
            setCameraState(cfg.cameraactive, 'is-active');
            return true;
        } catch (e) {
            status.textContent = cfg.camerarequired;
            setCameraState(cfg.camerarequired, 'is-required');
            return false;
        }
    };

    const getQuestionInputs = questionid =>
        [...document.querySelectorAll('.integrity-answer[data-questionid="' + questionid + '"]')];

    const questionIsAnswered = card => {
        if (!card) {
            return false;
        }
        return getQuestionInputs(card.dataset.questionid).some(node => node.checked);
    };

    const currentBounds = () => {
        if (perPage <= 0) {
            return {start: 0, end: questionCards.length};
        }
        const start = currentPage * perPage;
        return {start, end: Math.min(start + perPage, questionCards.length)};
    };

    const currentPageIsReady = () => {
        const {start, end} = currentBounds();
        const cards = questionCards.slice(start, end);
        return cards.length > 0 && cards.every(card => {
            const qid = String(card.dataset.questionid);
            return persistedAnswers.has(qid) || expiredQuestions.has(qid);
        });
    };

    const updateActionVisibility = () => {
        if (perPage <= 0) {
            previous.hidden = true;
            next.hidden = true;
            submit.hidden = !currentPageIsReady();
            return;
        }
        const lastPage = currentPage >= pageCount - 1;
        previous.hidden = currentPage === 0;
        next.hidden = lastPage || !currentPageIsReady();
        submit.hidden = !lastPage || !currentPageIsReady();
    };

    const syncOptionCards = questionid => {
        const nodes = getQuestionInputs(questionid);
        nodes.forEach(node => {
            const card = node.closest('.iq-option-card');
            if (card) {
                card.classList.toggle('is-selected', node.checked);
            }
        });
    };

    const updateQuestionAnsweredState = questionid => {
        const nodes = getQuestionInputs(questionid);
        const answered = nodes.some(node => node.checked);
        const nav = questionNav.find(item => item.dataset.questionid === String(questionid));
        if (nav) {
            nav.classList.toggle('is-answered', answered);
        }
        updateActionVisibility();
    };

    const updateNavigationState = (start, end) => {
        questionNav.forEach((item, index) => {
            item.classList.toggle('is-current', index >= start && index < end);
            if (index >= start && index < end) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
            if (perPage > 0) {
                item.disabled = Math.floor(index / perPage) > currentPage;
            }
        });

        const completedPosition = Math.max(1, end);
        const percentage = questionCards.length
            ? Math.min(100, Math.round((completedPosition / questionCards.length) * 100))
            : 0;
        if (progressText) {
            progressText.textContent = percentage + '%';
        }
        if (progressBar) {
            progressBar.style.width = percentage + '%';
        }
    };

    const clearQuestionTimer = () => {
        questionTimerToken++;
        if (questionTimerInterval) {
            window.clearInterval(questionTimerInterval);
            questionTimerInterval = null;
        }
    };

    const formatQuestionTime = seconds => {
        const safe = Math.max(0, seconds);
        const minutes = Math.floor(safe / 60);
        const secs = safe % 60;
        return String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    };

    const setQuestionExpired = card => {
        if (!card) {
            return;
        }
        const qid = String(card.dataset.questionid);
        expiredQuestions.add(qid);
        card.classList.add('is-time-expired');
        getQuestionInputs(qid).forEach(input => {
            input.disabled = true;
        });
        const display = card.querySelector('[data-questiontimer="' + qid + '"]');
        if (display) {
            display.textContent = cfg.questiontimeexpired;
            display.classList.add('is-expired');
        }
        const nav = questionNav.find(item => item.dataset.questionid === qid);
        if (nav) {
            nav.classList.add('is-expired');
        }
        updateActionVisibility();
    };

    const submitAttempt = async askConfirmation => {
        if (suspended || finishing) {
            return;
        }
        if (askConfirmation && !window.confirm(cfg.submitconfirm)) {
            return;
        }

        // Keep the camera alive long enough to capture the final evidence image.
        clearQuestionTimer();
        await snapshot('final');
        finishing = true;

        try {
            const data = await request('mod_integrityquiz_submit_attempt', common);

            // The server has now finalised the attempt and grade. End all local
            // monitoring before leaving the attempt screen.
            stopCamera();
            sessionStorage.removeItem(pageStorageKey);
            sessionStorage.removeItem('integrityquiz_pageid_' + cfg.attemptid);

            if (data.redirect) {
                // replace() prevents Back from reopening the finished attempt page.
                window.location.replace(data.redirect);
            } else {
                window.location.replace(M.cfg.wwwroot + '/mod/integrityquiz/view.php?id=' + cfg.cmid);
            }
        } catch (e) {
            finishing = false;
            secureStarted = true;
            window.location.reload();
        }
    };

    const advanceExpiredQuestion = async card => {
        if (!card || finishing || suspended) {
            return;
        }
        const index = parseInt(card.dataset.questionindex || '0', 10);
        const expiredPage = perPage > 0 ? Math.floor(index / perPage) : 0;
        if (expiredPage !== currentPage) {
            return;
        }
        if (currentPage < pageCount - 1) {
            currentPage++;
            renderPage();
        } else {
            await submitAttempt(false);
        }
    };

    const startQuestionTimer = async() => {
        clearQuestionTimer();
        if (!secureStarted || questionTimeLimit <= 0 || perPage !== 1 || finishing || suspended) {
            return;
        }

        const card = questionCards[currentPage];
        if (!card) {
            return;
        }
        const qid = String(card.dataset.questionid);
        const display = card.querySelector('[data-questiontimer="' + qid + '"]');
        const token = questionTimerToken;

        try {
            const data = await request('mod_integrityquiz_question_timer', {
                ...common,
                questionid: qid,
            });
            suspendIfNeeded(data);
            if (token !== questionTimerToken || finishing || suspended) {
                return;
            }
            if (data.expired) {
                setQuestionExpired(card);
                window.setTimeout(() => advanceExpiredQuestion(card), 350);
                return;
            }

            let remaining = Math.max(0, parseInt(data.remaining || 0, 10));
            if (display) {
                display.textContent = cfg.questiontimerlabel + ' ' + formatQuestionTime(remaining);
            }

            questionTimerInterval = window.setInterval(async() => {
                remaining--;
                if (display) {
                    display.textContent = cfg.questiontimerlabel + ' ' + formatQuestionTime(remaining);
                    display.classList.toggle('is-low', remaining <= 10);
                }
                if (remaining <= 0) {
                    clearQuestionTimer();
                    try {
                        const finalState = await request('mod_integrityquiz_question_timer', {
                            ...common,
                            questionid: qid,
                        });
                        suspendIfNeeded(finalState);
                    } catch (e) {
                        // The server will enforce the deadline on any later save.
                    }
                    setQuestionExpired(card);
                    await advanceExpiredQuestion(card);
                }
            }, 1000);
        } catch (e) {
            // Do not start a client-only timer if the authoritative server timer cannot be reached.
        }
    };

    function renderPage() {
        clearQuestionTimer();
        if (perPage <= 0) {
            questionCards.forEach(card => card.hidden = false);
            pageStatus.textContent = cfg.questionprogress
                .replace('{current}', questionCards.length)
                .replace('{total}', questionCards.length);
            updateNavigationState(0, questionCards.length);
            updateActionVisibility();
            return;
        }

        const start = currentPage * perPage;
        const end = Math.min(start + perPage, questionCards.length);
        questionCards.forEach((card, index) => {
            card.hidden = index < start || index >= end;
        });

        pageStatus.textContent = cfg.questionprogress
            .replace('{current}', end)
            .replace('{total}', questionCards.length);
        sessionStorage.setItem(pageStorageKey, String(currentPage));
        updateNavigationState(start, end);
        updateActionVisibility();

        const currentNav = questionNav[Math.max(0, start)];
        centerQuestionNavItem(currentNav);

        if (secureStarted) {
            startQuestionTimer();
        }
    }

    questionCards.forEach(card => {
        syncOptionCards(card.dataset.questionid);
        updateQuestionAnsweredState(card.dataset.questionid);
        if (questionIsAnswered(card)) {
            persistedAnswers.add(String(card.dataset.questionid));
        }
    });
    updateActionVisibility();

    questionNav.forEach(item => item.addEventListener('click', () => {
        const index = parseInt(item.dataset.questionindex || '0', 10);
        if (perPage <= 0) {
            const card = questionCards[index];
            if (card) {
                questionNav.forEach(nav => nav.classList.remove('is-current'));
                item.classList.add('is-current');
                if (!document.body.classList.contains('iq-attempt-viewport-locked')) {
                    card.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            }
            return;
        }

        const targetPage = Math.floor(index / perPage);
        if (targetPage > currentPage) {
            return;
        }
        currentPage = targetPage;
        renderPage();
    }));

    startCamera();

    enter.addEventListener('click', async() => {
        if (cfg.requirecamera && !stream) {
            if (!(await startCamera())) {
                return;
            }
        }
        secureStarted = true;
        document.getElementById('integrity-precheck').hidden = true;
        questionsContainer.hidden = false;
        lockAttemptViewport();
        renderPage();
        await snapshot('start');
        scheduleSnapshot();
    });

    document.addEventListener('visibilitychange', () => {
        if (secureStarted && cfg.suspendontabswitch && document.hidden && !finishing) {
            event('tabswitch', 'Assessment page became hidden');
        }
    });

    document.querySelectorAll('.integrity-answer').forEach(element => element.addEventListener('change', async() => {
        if (suspended || finishing || element.disabled) {
            return;
        }
        const qid = element.dataset.questionid;
        const nodes = getQuestionInputs(qid);
        let value;
        if (element.type === 'checkbox') {
            value = nodes.filter(node => node.checked).map(node => node.value);
        } else {
            const checked = nodes.find(node => node.checked);
            value = checked ? checked.value : '';
        }

        persistedAnswers.delete(String(qid));
        syncOptionCards(qid);
        updateQuestionAnsweredState(qid);

        try {
            const saved = await request('mod_integrityquiz_save_response', {
                ...common,
                questionid: qid,
                response: JSON.stringify(value),
            });
            suspendIfNeeded(saved);
            if (!suspended && nodes.some(node => node.checked)) {
                persistedAnswers.add(String(qid));
            }
            updateActionVisibility();
        } catch (e) {
            if (questionTimeLimit > 0) {
                startQuestionTimer();
            }
        }
    }));

    previous.addEventListener('click', () => {
        if (currentPage > 0) {
            currentPage--;
            renderPage();
        }
    });

    next.addEventListener('click', () => {
        if (!currentPageIsReady()) {
            return;
        }
        if (currentPage < pageCount - 1) {
            currentPage++;
            renderPage();
        }
    });

    submit.addEventListener('click', async() => {
        if (!currentPageIsReady()) {
            return;
        }
        await submitAttempt(true);
    });

    window.setInterval(async() => {
        if (finishing) {
            return;
        }
        try {
            suspendIfNeeded(await request('mod_integrityquiz_heartbeat', {...common, pageid}));
        } catch (e) {
            // Keep the attempt page usable during short network interruptions.
        }
    }, 10000);

    const renderTimer = () => {
        if (cfg.timelimit <= 0) {
            timer.textContent = '--:--:--';
            return;
        }
        const left = Math.max(0, cfg.timelimit - (Math.floor(Date.now() / 1000) - cfg.timestart));
        const hours = Math.floor(left / 3600);
        const minutes = Math.floor((left % 3600) / 60);
        const seconds = left % 60;
        timer.textContent = String(hours).padStart(2, '0') + ':'
            + String(minutes).padStart(2, '0') + ':'
            + String(seconds).padStart(2, '0');
        if (left === 0 && !suspended && !finishing) {
            submitAttempt(false);
        }
    };
    renderTimer();
    window.setInterval(renderTimer, 1000);

    window.addEventListener('resize', syncAttemptViewportHeight);

    window.addEventListener('beforeunload', () => {
        clearQuestionTimer();
        unlockAttemptViewport();
        stopCamera();
    });
};
