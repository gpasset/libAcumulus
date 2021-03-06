<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Class SplitKnownDiscountLine implements a vat completor strategy by using the
 * Meta::LineDiscountAmountInc tags to split a discount line over several
 * lines with different vat rates as it may be considered as the total discount
 * over multiple products that may have different vat rates.
 *
 * Preconditions:
 * - lines2Complete contains 1 line that may be split.
 * - There should be other lines that have a Meta::LineDiscountAmountInc tag
 *   and an exact vat rate, and these amounts must add up to the amount of the
 *   line that is to be split.
 * - This strategy should be executed early as it is a sure and controlled win
 *   and can even be used as a partial solution.
 *
 * Strategy:
 * The amounts in the lines that have a Meta::LineDiscountAmountInc tag are
 * summed by their vat rates and these "discount amounts per vat rate" are used
 * to create the lines that replace the single discount line.
 *
 * Current usages:
 * - Magento
 * - PrestaShop but only if:
 *   - getOrderDetailTaxes() works correctly and thus if table order_detail_tax
 *     does have (valid) content.
 *   - if no discount on shipping and other fees as these do not end up in table
 *     order_detail_tax.
 */
class SplitKnownDiscountLine extends CompletorStrategyBase
{
    /**
     * This strategy should be tried first as it is a controlled but possibly
     * partial solution. Controlled in the sense that it will only be applied to
     * lines where it can and should be applied. So no chance of returning a
     * false positive.
     *
     * It should come before the SplitNonMatchingLine as this one depends on
     * more specific information being available and thus is more controlled
     * than that other split strategy.
     *
     * @var int
     */
    static public $tryOrder = 10;

    /** @var float */
    protected $knownDiscountAmountInc;

    /** @var float */
    protected $knownDiscountVatAmount;

    /** @var float[] */
    protected $discountsPerVatRate;

    /** @var array */
    protected $splitLine;

    /** @var int */
    protected $splitLineKey;

    /** @var int */
    protected $splitLineCount;

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->splitLineCount = 0;
        foreach ($this->lines2Complete as $key => $line2Complete) {
            if (!empty($line2Complete[Meta::StrategySplit])) {
                $this->splitLine = $line2Complete;
                $this->splitLineKey = $key;
                $this->splitLineCount++;
            }
        }

        if ($this->splitLineCount === 1) {
            $this->discountsPerVatRate = array();
            $this->knownDiscountAmountInc = 0.0;
            $this->knownDiscountVatAmount = 0.0;
            foreach ($this->invoice['customer']['invoice']['line'] as $line) {
                if (isset($line[Meta::LineDiscountAmountInc]) && Completor::isCorrectVatRate($line[Meta::VatRateSource])) {
                    $this->knownDiscountAmountInc += $line[Meta::LineDiscountAmountInc];
                    $this->knownDiscountVatAmount += $line[Meta::LineDiscountAmountInc] / (100.0 + $line[Tag::VatRate]) * $line[Tag::VatRate];
                    $vatRate = sprintf('%.3f', $line[Tag::VatRate]);
                    if (isset($this->discountsPerVatRate[$vatRate])) {
                        $this->discountsPerVatRate[$vatRate] += $line[Meta::LineDiscountAmountInc];
                    } else {
                        $this->discountsPerVatRate[$vatRate] = $line[Meta::LineDiscountAmountInc];
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function checkPreconditions()
    {
        $result = false;
        if ($this->splitLineCount === 1) {
            if ((isset($this->splitLine[Tag::UnitPrice]) && Number::floatsAreEqual($this->splitLine[Tag::UnitPrice], $this->knownDiscountAmountInc - $this->knownDiscountVatAmount))
                || (isset($this->splitLine[Meta::UnitPriceInc]) && Number::floatsAreEqual($this->splitLine[Meta::UnitPriceInc], $this->knownDiscountAmountInc))
            ) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->linesCompleted = array($this->splitLineKey);
        return $this->splitDiscountLine();
    }

    /**
     * @return bool
     */
    protected function splitDiscountLine()
    {
        $this->description = "SplitKnownDiscountLine({$this->knownDiscountAmountInc}, {$this->knownDiscountVatAmount})";
        $this->replacingLines = array();
        foreach ($this->discountsPerVatRate as $vatRate => $discountAmountInc) {
            $line = $this->splitLine;
            $line[Tag::Product] = "{$line[Tag::Product]} ($vatRate%)";
            $line[Meta::UnitPriceInc] = $discountAmountInc;
            unset($line[Tag::UnitPrice]);
            $this->completeLine($line, $vatRate);
        }
        return true;
    }
}
