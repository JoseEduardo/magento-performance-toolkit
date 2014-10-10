<?php
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */

namespace Magento\ToolkitFramework\ImportExport\Fixture\Tax\Import;

use \Mage;

class CsvHandler
{
    /**
     * Copy-paste from controller
     *
     * @param string $fileName
     *
     * @throws \Exception
     */
    public function importRates($fileName)
    {
        $csvObject  = new \Varien_File_Csv();
        $csvData = $csvObject->getData($fileName);

        /** checks columns */
        $csvFields  = array(
            0   => Mage::helper('tax')->__('Code'),
            1   => Mage::helper('tax')->__('Country'),
            2   => Mage::helper('tax')->__('State'),
            3   => Mage::helper('tax')->__('Zip/Post Code'),
            4   => Mage::helper('tax')->__('Rate'),
            5   => Mage::helper('tax')->__('Zip/Post is Range'),
            6   => Mage::helper('tax')->__('Range From'),
            7   => Mage::helper('tax')->__('Range To')
        );


        $stores = array();
        $unset = array();
        $storeCollection = Mage::getModel('core/store')->getCollection()->setLoadDefault(false);
        $cvsFieldsNum = count($csvFields);
        $cvsDataNum   = count($csvData[0]);
        for ($i = $cvsFieldsNum; $i < $cvsDataNum; $i++) {
            $header = $csvData[0][$i];
            $found = false;
            foreach ($storeCollection as $store) {
                if ($header == $store->getCode()) {
                    $csvFields[$i] = $store->getCode();
                    $stores[$i] = $store->getId();
                    $found = true;
                }
            }
            if (!$found) {
                $unset[] = $i;
            }

        }

        $regions = array();

        if ($unset) {
            foreach ($unset as $u) {
                unset($csvData[0][$u]);
            }
        }
        if ($csvData[0] == $csvFields) {
            /** @var $helper \Mage_Adminhtml_Helper_Data */
            $helper = Mage::helper('adminhtml');

            foreach ($csvData as $k => $v) {
                if ($k == 0) {
                    continue;
                }

                //end of file has more then one empty lines
                if (count($v) <= 1 && !strlen($v[0])) {
                    continue;
                }
                if ($unset) {
                    foreach ($unset as $u) {
                        unset($v[$u]);
                    }
                }

                if (count($csvFields) != count($v)) {
                    throw new \Exception('Invalid file upload attempt');
                }

                $country = Mage::getModel('directory/country')->loadByCode($v[1], 'iso2_code');
                if (!$country->getId()) {
                    throw new \Exception('One of the country has invalid code.');
                    continue;
                }

                if (!isset($regions[$v[1]])) {
                    $regions[$v[1]]['*'] = '*';
                    $regionCollection = Mage::getModel('directory/region')->getCollection()
                        ->addCountryFilter($v[1]);
                    if ($regionCollection->getSize()) {
                        foreach ($regionCollection as $region) {
                            $regions[$v[1]][$region->getCode()] = $region->getRegionId();
                        }
                    }
                }

                if (!empty($regions[$v[1]][$v[2]])) {
                    $rateData  = array(
                        'code'           => $v[0],
                        'tax_country_id' => $v[1],
                        'tax_region_id'  => ($regions[$v[1]][$v[2]] == '*') ? 0 : $regions[$v[1]][$v[2]],
                        'tax_postcode'   => (empty($v[3]) || $v[3]=='*') ? null : $v[3],
                        'rate'           => $v[4],
                        'zip_is_range'   => $v[5],
                        'zip_from'       => $v[6],
                        'zip_to'         => $v[7]
                    );

                    $rateModel = Mage::getModel('tax/calculation_rate')->loadByCode($rateData['code']);
                    foreach($rateData as $dataName => $dataValue) {
                        $rateModel->setData($dataName, $dataValue);
                    }

                    $titles = array();
                    foreach ($stores as $field=>$id) {
                        $titles[$id] = $v[$field];
                    }

                    $rateModel->setTitle($titles);
                    $rateModel->save();
                }
            }
        } else {
            throw new \Exception('Invalid file format upload attempt');
        }
    }
}
