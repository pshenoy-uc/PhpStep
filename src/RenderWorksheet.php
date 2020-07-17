<?php

/**
 * @link http://www.vishwayon.com/
 * @copyright Copyright (c) 2020 Vishwayon Software Pvt Ltd
 * @license MIT
 */

namespace PhpStep;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use PhpStep\base\PatternType;

/**
 * RenderWorksheet puts together the Xlsx template worksheet and the json data source
 * This class applies the Json data to the worksheet
 * .
 * All changes are reflected in the original template file. Make sure that you 
 * have created a copy of the original file before submission or use the writer after
 * applying template and save the file with a new name
 * 
 * @author girish
 */
class RenderWorksheet {
    /**
     * Contains a collection of regex patterns to search
     * @var array
     */
    private $patterns = [
        'field' => '/\$F\{\S{1,}\}/',  // Field with pattern $F{field_name}
        'each' => '/\$Each\{\S{1,}\}/' // Each with pattern $Each{array_name}
    ];
    
    /**
     * Applies data to the requested worksheet
     * Data should always be a model with accessible properties
     * 
     * @param Worksheet $worksheet      The worksheet template
     * @param mixed $data               A data structure/model that contains properties to be applied to the worksheet
     */
    public function applyData(Worksheet $worksheet, $model) {
        $hRow = $worksheet->getHighestRow();
        $hCol = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
        for ($row = 1; $row <= $hRow; $row++) {
            for ($col = 1; $col <= $hCol; $col++) {
                $cell = $worksheet->getCellByColumnAndRow($col, $row);
                $ptype = $this->parsePattern($worksheet, $cell);
                if($ptype->getType() == PatternType::PATTERN_TYPE_FIELD) {
                    $this->setCellData($cell, $ptype, $model);
                } elseif ($ptype->getType() == PatternType::PATTERN_TYPE_EACH) {
                    //Store Each Row marker
                    $eachRowMarker = $row; 
                    $row++;
                    if (property_exists($model, $ptype->propName)) {
                        $prop = $ptype->propName;
                        $childItems = $model->$prop;
                        foreach($childItems as $itm) {
                            // insert row in sheet
                            $worksheet->insertNewRowBefore($row, 1);
                            foreach($ptype->tranInfo as $cc => $cptype) {
                                $cell = $worksheet->getCellByColumnAndRow($cc, $row);
                                $this->setCellData($cell, $cptype, (object)$itm);
                                // Copy cell styles to inserted row
                                $worksheet->duplicateStyle($worksheet->getStyleByColumnAndRow($cc, $row+1), Coordinate::stringFromColumnIndex($cc).$row);
                            }
                            $row++;
                        }
                        // Remove row->field markers
                        $worksheet->removeRow($eachRowMarker);
                        $worksheet->removeRow($row-1);
                    }
                }
                $hRow = $worksheet->getHighestRow();
            }
        }
    }
    
    /**
     * Returns the patternType from the cell
     * @param Cell $cell
     * @return string
     */
    private function parsePattern(Worksheet $worksheet, Cell $cell): PatternType {
        $val = $cell->getValue();
        $pType = new PatternType(PatternType::PATTERN_TYPE_NONE);
        if (preg_match($this->patterns['field'], $val, $matched)) { 
            $pType = new PatternType(PatternType::PATTERN_TYPE_FIELD);
            $pType->propName = strtr($matched[0], [
                    '$F{' => '', '}' => ''
                ]);
            return $pType;
        } elseif (preg_match($this->patterns['each'], $val, $matched)) {
            $pType = new PatternType(PatternType::PATTERN_TYPE_EACH);
            $pType->propName = strtr($matched[0], [
                    '$Each{' => '', '}' => ''
                ]);
            $pType->tranInfo = $this->buildTranInfo($worksheet, $cell->getRow());
        }
        return $pType;
    }
    
    private function setCellData(Cell $cell, PatternType $ptype, $model) {
        if ($ptype->getType() != PatternType::PATTERN_TYPE_NONE && property_exists($model, $ptype->propName)) {
            $prop = $ptype->propName;
            $cell->setValue($model->$prop);
        }
    }
    
    private function buildTranInfo(Worksheet $worksheet, int $eachRowMarker): array {
        // The fields for binding each would always be listed in the next row
        $row = $eachRowMarker + 1;
        $hCol = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
        // Create prop range
        $propRange = [];
        for($cc = 1; $cc <= $hCol; $cc++) {
            $cCell = $worksheet->getCellByColumnAndRow($cc, $row);
            $propRange[$cc] = $this->parsePattern($worksheet, $cCell);
        }
        return $propRange;
    }
    
}
