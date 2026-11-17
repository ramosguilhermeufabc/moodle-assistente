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
 * Version metadata for local_assistente.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_assistente';
// Formato AAAAMMDDXX (plano 002, Q-002-02). Depois da primeira instalação (T013),
// toda mudança em db/*.php exige incremento e a de esquema exige db/upgrade.php (DEC-29).
$plugin->version = 2026110202;
// Linha de base do ramo 5.2 (data de ramificação 2026-04-20). Valor a confirmar na
// validação do ambiente (T013, CL-01): o Moodle de referência é 2026042004.03.
$plugin->requires = 2026042000;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.0-dev';
