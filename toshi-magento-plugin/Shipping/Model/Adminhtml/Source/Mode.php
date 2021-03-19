<?php

namespace Toshi\Shipping\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class Mode
 */
class Mode implements ArrayInterface
{
    const MODE_SHIPPING = 'shipping';
    const MODE_TRY_BEFORE_YOU_BUY = 'try_before_you_buy';

    /**
     * Possible mode types
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::MODE_TRY_BEFORE_YOU_BUY,
                'label' => 'Try Before You Buy',
            ],
            [
                'value' => self::MODE_SHIPPING,
                'label' => 'Shipping'
            ]
        ];
    }
}
