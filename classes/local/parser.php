<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Structured bulk question parser.
 *
 * @package    mod_integrityquiz
 * @copyright  2026 Muhammad Haseeb, FoneRep Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_integrityquiz\local;

defined('MOODLE_INTERNAL') || die();

class parser {
    public static function parse(string $text): array {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') { return []; }
        $blocks = preg_split('/\n\s*---+\s*\n|(?=\[QUESTION\s+\d+\])/i', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", trim($block))), fn($v) => $v !== ''));
            $q = ['qtype' => 'multichoice', 'questiontext' => '', 'options' => [], 'correct' => [], 'feedback' => '', 'marks' => 1.0];
            foreach ($lines as $line) {
                if (preg_match('/^\[QUESTION/i', $line)) { continue; }
                if (preg_match('/^TYPE:\s*(.+)$/i', $line, $m)) {
                    $type = strtoupper(trim($m[1]));
                    $q['qtype'] = in_array($type, ['TRUEFALSE','TF'], true) ? 'truefalse' : ($type === 'MULTISELECT' ? 'multiselect' : 'multichoice');
                } else if (preg_match('/^(?:TEXT|QUESTION):\s*(.+)$/i', $line, $m)) {
                    $q['questiontext'] = trim($m[1]);
                } else if (preg_match('/^([A-J]):\s*(.+)$/i', $line, $m)) {
                    $q['options'][strtoupper($m[1])] = trim($m[2]);
                } else if (preg_match('/^CORRECT:\s*(.+)$/i', $line, $m)) {
                    $q['correct'] = array_values(array_filter(array_map(fn($v) => strtoupper(trim($v)), preg_split('/[,;]+/', $m[1]))));
                } else if (preg_match('/^MARKS?:\s*([0-9.]+)$/i', $line, $m)) {
                    $q['marks'] = max(0.01, (float)$m[1]);
                } else if (preg_match('/^FEEDBACK:\s*(.+)$/i', $line, $m)) {
                    $q['feedback'] = trim($m[1]);
                }
            }
            if ($q['qtype'] === 'truefalse' && !$q['options']) { $q['options'] = ['TRUE' => 'True', 'FALSE' => 'False']; }
            if ($q['questiontext'] && $q['correct'] && $q['options']) { $out[] = $q; }
        }
        return $out;
    }
}
