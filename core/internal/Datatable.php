<?php

/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 * 
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0  
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * For more information : contact@centreon.com
 * 
 */

namespace Centreon\Internal;

/**
 * @author Lionel Assepo <lassepo@centreon.com>
 * @package Centreon
 * @subpackage Core
 */
class Datatable
{
    /**
     *
     * @var string 
     */
    protected static $hook = '';

    /**
     * Parameters send to hook
     *
     * A var _ids is add to this parameters and content the list of id of the table
     * 
     * @var array
     */
    protected static $hookParams = array();
    
    /**
     *
     * @var string 
     */
    protected static $objectId = '';
    
    /**
     *
     * @var string 
     */
    protected static $objectName = '';

    /**
     *
     * @var string 
     */
    protected $objectModelClass;
    
    /**
     *
     * @var string 
     */
    protected static $dataprovider = '';
    
    /**
     *
     * @var array 
     */
    public static $fieldList = array();
    
    /**
     *
     * @var array 
     */
    protected $options = array();
    
    /**
     *
     * @var array 
     */
    public static $columns = array();
    
    /**
     *
     * @var array 
     */
    protected static $configuration = array();
    
    /**
     *
     * @var array 
     */
    protected $specialFields = array();
    
    /**
     *
     * @var array 
     */
    protected $params = array();
    
    /**
     *
     * @var type 
     */
    protected $rawDatasFromDb;
    
    /**
     *
     * @var array 
     */
    protected static $additionnalDatasource = null;
    
    /**
     *
     * @var array 
     */
    private static $nonDatatableParams = array(
        'cast',
        'searchParam',
        'source',
        'searchvalues',
        'DT_RowData',
        'DT_RowId'
    );
    
    
    /**
     * 
     */
    protected static $rowIdColumn = array();

    /**
     * Extra parameters for datatable
     *
     * @var array
     */
    protected static $extraParams = array();

    /**
     * 
     * @param array $params
     * @param string $objectModelClass
     */
    public function __construct($params, $objectModelClass = '')
    {
        $this->params = $params;
        $this->objectModelClass = $objectModelClass;
    }
    
    /**
     * 
     * @return array
     */
    public function getDatas()
    {
        $provider = static::$dataprovider;

        $datasFromDb = $provider::loadDatas(
            $this->params,
            static::$columns,
            $this->specialFields,
            get_class($this),
            $this->objectModelClass,
            static::$additionnalDatasource,
            isset(static::$aFieldNotAuthorized) ? static::$aFieldNotAuthorized : array()
        );
       
        
        static::addAdditionnalDatas($datasFromDb['datas']);
        static::processHooks($datasFromDb['datas']);

        // Add RowId
        if (count(static::$rowIdColumn) > 0) {
            foreach ($datasFromDb['datas'] as &$datas) {
                $datas['DT_RowData'] = array(
                    'id' => $datas[static::$rowIdColumn['id']],
                    'name' => $datas[static::$rowIdColumn['name']]
                );
                $datas['DT_RowId'] = $datas[static::$rowIdColumn['id']];
            }
        }

        $this->formatDatas($datasFromDb['datas']);

        $sendableDatas = $this->prepareDatasForSending($datasFromDb);
        
        return $sendableDatas;
    }
    
    /**
     * 
     * @param array $datasToFormat
     */
    protected function formatDatas(&$datasToFormat)
    {
        
    }
    
    /**
     * 
     * @param array $datasToSend
     * @return array
     */
    protected function prepareDatasForSending($datasToSend)
    {
        // Cast result
        $datasToSend['datas'] = $this->castResult($datasToSend['datas']);
        
        // format the data before returning
        $finalDatas = array(
            "draw" => intval($this->params['draw']),
            "recordsTotal" => count($datasToSend['datas']),
            "recordsFiltered" => $datasToSend['nbOfTotalDatas'],
            "data" => $datasToSend['datas']
        );
       
        return $finalDatas;
    }
    
    /**
     * 
     * @param array $resultSet
     */
    protected static function addAdditionnalDatas(&$resultSet)
    {
        
    }
    
    /**
     * 
     * @return array
     */
    public static function getHeader()
    {
        $columnHeader = "";
        $columnSearchIndex = array();
        $nbFixedTr = count(static::$columns);
        
        $hookArr = static::getHookArray();

        foreach ($hookArr as $hook) {
            $hookData = Hook::execute($hook, array());
            foreach ($hookData as $data) {
                $columnName = $data['columnName'];
                static::$columns[] = array(
                    'name' => $columnName,
                    'title' => $columnName,
                    'data' => $columnName,
                    'orderable' => false,
                    'searchable' => false
                );
            }
        }

        foreach (static::$columns as $column) {
            static::$fieldList[] = $column['name'];
            $columnHeader .= '{';
            $searchable = false;
            
            foreach ($column as $key => $value) {
                if (!in_array($key, self::$nonDatatableParams)) {
                    if (is_string($value)) {
                        $columnHeader .= '"' . $key . '":"' . addslashes($value) . '",';
                    } elseif (is_bool($value)) {
                        if ($value === true) {
                            $columnHeader .= '"' . $key . '":true,';
                        } else {
                            $columnHeader .= '"' . $key . '":false,';
                        }
                    } else {
                        $columnHeader .= '"' . $key . '":' . (string)$value . ',';
                    }

                    if (($key === 'searchable')) {
                        $searchable = $value;
                    }
                }
            }
            
            $columnHeader .= "},\n";
            if ($searchable) {
                $searchParam = array ('type' => 'text');
                if (isset($column['searchParam'])) {
                    $searchParam = array_merge($searchParam, $column['searchParam']);
                }
                if (isset($column['searchLabel'])) {
                    $searchParam['searchLabel'] = $column['searchLabel'];
                } else {
                    $replaceChar = array(' ', '/', '\\');
                    $firstReplace = str_replace($replaceChar, '_', $column['title']);
                    $searchParam['searchLabel'] = strtolower(preg_replace('/(\_)+/', '_', $firstReplace));
                }
                $searchParam['title'] = $column['title'];
                $searchParam['colIndex'] = array_search($column['name'], static::$fieldList);
                
                if (!isset($searchParam['main'])) {
                    $searchParam['main'] = false;
                }
                
                $columnSearchIndex[addslashes($column['data'])] = $searchParam;
            }
            
        }
        
        return array(
            'columnHeader' => $columnHeader,
            'columnSearch' => $columnSearchIndex,
            'nbFixedTr' => $nbFixedTr
        );
    }

    /**
     * 
     * @return string
     */
    public static function getConfiguration()
    {
        $configurationParams = "";
        
        foreach (static::$configuration as $configName => $configEntry) {
            
            if ($configName == 'order') {
                $configEntry = self::initOrder($configEntry);
            }
            
            if ($configName == 'searchCols') {
                $configEntry = self::initSearch($configEntry);
            }
                       
            $configEntry = (is_array($configEntry)) ? json_encode($configEntry) : $configEntry;
            
            if (is_bool($configEntry)) {
                if ($configEntry === true) {
                    $configEntry = 'true';
                } else {
                    $configEntry = 'false';
                }
            }
            
            $configurationParams .= '"' . $configName . '":' . $configEntry . ",\n";
        }
        
        return trim($configurationParams);
    }

    /**
     * Get the list of extra parameters for datatable
     *
     * @return array
     */
    public static function getExtraParams()
    {
        return static::$extraParams;
    }

    /**
     * 
     * @param type $configEntry
     * @return array
     */
    private static function initSearch($configEntry)
    {
        $rawSeachTable = array();
        $listOfSearchField = array_keys(static::$configuration['searchCols']);
        foreach (static::$columns as $column) {
            if (in_array($column['name'], $listOfSearchField)) {
                $rawSeachTable[] = array('sSearch' => $configEntry[$column['name']]);
            } else {
                $rawSeachTable[] = null;
            }
        }
        return $rawSeachTable;
    }
    
    /**
     * 
     * @param array $configEntry
     * @return string
     */
    private static function initOrder($configEntry)
    {
        $line = "[";
        foreach ($configEntry as $order) {
            $line .= "[" . array_search($order[0], static::$fieldList) . ", '". $order[1] ."'],";
        }
        return rtrim($line, ',') . ']';
    }
    
    /**
     * 
     * @param type $datas
     * @return type
     */
    public static function castResult($datas)
    {
        try {
            $columnsToCast = array();
            foreach (static::$columns as $column) {
                if (isset($column['cast'])) {
                    $columnsToCast[$column['name']] = $column['cast'];
                    $columnsToCast[$column['name']]['caster'] = 'add'.ucwords($column['cast']['type']);
                }
            }

            foreach ($datas as &$singleData) {
                $originalData = $singleData;
                foreach ($columnsToCast as $colName => $colCast) {
                    if (preg_match('/[A-z]\./', $colName)) {
                        $a = explode('.', $colName);
                        array_shift($a);
                        $a = implode('.', $a);
                    } else {
                        $a = $colName;
                    }
                    $extra = array();
                    if (isset($colCast['extra'])) {
                        $extra = $colCast['extra'];
                    }
                    $singleData[$a] =  self::$colCast['caster']($a, $originalData, $colCast['parameters'], $extra);
                }
            }
            
            return $datas;
        } catch (\Exception $e) {
            var_dump($e);
        }
    }

    /**
     * 
     * @param type $field
     * @param type $values
     * @param type $cast
     * @return type
     */
    public static function addUrl($field, $values, $cast)
    {
        if (isset($values['DT_RowData'])) {
            unset($values['DT_RowData']);
            unset($values['DT_RowId']);
        }
        
        $castedElement = \array_map(
            function ($n) {
                return "::$n::";
            },
            array_keys($values)
        );
        
        $finalRoute = self::parseUrl($cast, $castedElement, $values);
        
        $linkName =  str_replace($castedElement, $values, $cast['linkName']);

        $class = '';
        if (isset($cast['styleClass'])) {
            $class .=$cast['styleClass'];
        }
        
        return '<a class="' . $class . '" href="' . $finalRoute . '">' . $linkName . '</a>';
    }
    
    /**
     * 
     * @param type $field
     * @param type $values
     * @param type $cast
     * @return type
     */
    public static function addCheckbox($field, $values, $cast)
    {
        if (isset($values['DT_RowData'])) {
            unset($values['DT_RowData']);
            unset($values['DT_RowId']);
        }
        
        $castedElement = \array_map(
            function ($n) {
                return "::$n::";
            },
            array_keys($values)
        );
        $datasource = static::$datasource;
        $uniqueField = $datasource::getUniqueLabelField();
        $object = ucwords(str_replace('_', '', $field));
        $className = 'all' . static::$objectName . 'Box';
        if (isset($cast['styleClass'])) {
            $className = $cast['styleClass'];
        }
        $input = '<input class="' . $className . '" '
            . 'id="'. static::$objectName .'::'. $field .'::" '
            . 'name="'. static::$objectName .'[]" '
            . 'type="checkbox" '
            . 'value="::'. $field .'::" '
            . 'data-name="' . htmlentities($values[$uniqueField]) . '"';
        if (isset($cast['data'])) {
            $input .= self::setData($cast['data'], $castedElement, $values);
        }
        $input .= '>';
        
        return str_replace($castedElement, $values, $input);
    }
    
    /**
     * 
     * @param type $field
     * @param type $values
     * @param type $cast
     * @return type
     */
    public static function addSelect($field, $values, $cast, $extra = array())
    {
        $myElement = "";
        static $previousValue;
        if (isset($cast['selecttype']) && ($cast['selecttype'] != 'none')) {
            $subCaster = 'add'.ucwords($cast['selecttype']);
            $myElement = static::$subCaster($field, $values, $cast['parameters'][$values[$field]]['parameters']);
        } elseif (isset($values[$field])) {
            if (isset($cast[$values[$field]])) {
                $myElement = $cast[$values[$field]];
                if (isset($extra['groupable']) && $extra['groupable']) {
                    if ($myElement === $previousValue) {
                        $myElement = "";
                    } else {
                        $previousValue = $myElement;
                    }
                }
            } else {
                $myElement = $values[$field];
            }
        }
        
        return $myElement;
    }
    
    /**
     * 
     * @param type $field
     * @param type $values
     * @param type $cast
     * @return type
     */
    public static function addDate($field, $values, $cast)
    {

        return date($cast['date'], $values[$field]);
    }

    /**
     * 
     * @param type $field
     * @param type $values
     * @param type $cast
     * @return type
     */
    public static function addTemplate($field, $values, $cast)
    {
        if (isset($values['DT_RowData'])) {
            unset($values['DT_RowData']);
            unset($values['DT_RowId']);
        }
        
        $castedElement = \array_map(
            function ($n) {
                return "::$n::";
            },
            array_keys($values)
        );

        $returnString = $cast['tmpl'];
        if (isset($cast['route'])) {
            $finalRoute = self::parseUrl($cast, $castedElement, $values);
            $returnString = str_replace("::link::", $finalRoute, $returnString);
        }
        
        return str_replace($castedElement, $values, $returnString);
    }

    /**
     * 
     * @param type $resultSet
     */
    public static function processHooks(&$resultSet)
    {
       	$params = static::$hookParams;
        $params['_ids'] = array();
        foreach ($resultSet as $set) {
            if (isset($set[static::$objectId])) {
                $params['_ids'][] = $set[static::$objectId];
            }
        }
        
        $hookArr = static::getHookArray();

        foreach ($hookArr as $hook) {
            $hookData = Hook::execute($hook, $params);
            foreach ($hookData as $data) {
                $columnName = $data['columnName'];
                foreach ($data['values'] as $k => $value) {
                    foreach ($resultSet as $key => $set) {
                        if ($set[static::$objectId] == $k) {
                            $resultSet[$key][$columnName] = $value;
                        }
                    }
                }
            }
        }
    }

    /**
     * Return the hook array
     *
     * @return array
     */
    protected static function getHookArray()
    {
        $hookArr = array();

        /* If hook is string, we put it in an array */
        if (!isset(static::$hook) || static::$hook == '') {
            return $hookArr;
        }

        if (!is_array(static::$hook)) {
            $hookArr = array(static::$hook);
        } else {
            $hookArr = static::$hook;
        }
        return $hookArr;
    }
    
    /**
     * 
     * @param type $datas
     * @param type $castedElement
     * @param type $values
     * @return string
     */
    public static function setData($datas, $castedElement, $values)
    {
        $dataStr = '';
        foreach ($datas as $key => $info) {
            if (is_array($info)) {
                if ($info['type'] == 'url') {
                    $info = self::parseUrl($info, $castedElement, $values);
                }
            }
            $dataStr .= ' data-' . $key . '="' . $info . '"';
        }
        return $dataStr;
    }

    /**
     * Parse a array for generate the url
     *
     * @param array $params The url parameters
     * @param array $castedElement The element converted
     * @param array $values The values of row
     * @return string
     */
    protected static function parseUrl($params, $castedElement, $values)
    {
        if (isset($values['DT_RowData'])) {
            unset($values['DT_RowData']);
            unset($values['DT_RowId']);
        }
        
        $routeParams = array();
        if (isset($params['routeParams']) && is_array($params['routeParams'])) {
            $routeParams = str_replace($castedElement, $values, $params['routeParams']);
        }

        $finalRoute = str_replace(
            "//",
            "/",
            Di::getDefault()
                ->get('router')
                ->getPathFor($params['route'], $routeParams)
        );

        return $finalRoute;
    }
}
