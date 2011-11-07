<?php
/**
 * Copyright 2005-2011 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once "class/centreonWidget/Params.class.php";

class CentreonWidgetParamsCompare extends CentreonWidgetParams
{
    public function __construct($db, $quickform, $userId)
    {
        parent::__construct($db, $quickform, $userId);
    }

    public function init($params)
    {
        parent::init($params);
        if (isset($this->quickform)) {
            $elems = array();
            $operands = array(null      => null,
            				  "gt"      => ">",
                              "lt"      => "<",
                              "gte"     => ">=",
                              "lte"     => "<=",
                              "eq"      => "=",
                              "ne"      => "!=",
                              "like"    => "LIKE",
                              "notlike" => "NOT LIKE");
            $elems[] = $this->quickform->addElement('select', 'op_'.$params['parameter_id'], '', $operands);
            $elems[] = $this->quickform->addElement('text',
            											  'cmp_'.$params['parameter_id'],
                                                          $params['parameter_name'],
                                                          array("size" => 30));
            $this->element = $this->quickform->addGroup($elems, 'param_'.$params['parameter_id'], $params['parameter_name'], '&nbsp;');
        }
    }

    public function setValue($params)
    {
        $userPref = $this->getUserPreferences($params);
        if (isset($userPref)) {
            $target = $userPref;
        } elseif (isset($params['default_value']) && $params['default_value'] != "") {
            $target = $params['default_value'];
        }
        if (isset($target)) {
            if (preg_match("/(gt |lt |gte |lte |eq |ne |like |notlike )(.+)/", $target, $matches)) {
                $op = trim($matches[1]);
                $val = trim($matches[2]);
            }
            if (isset($op) && isset($val)) {
                $this->quickform->setDefaults(array('op_'.$params['parameter_id'] => $op,
                                                    'cmp_'.$params['parameter_id']=> $val));
            }
        }
    }
}