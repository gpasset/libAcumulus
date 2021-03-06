<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Meta;

/**
 * The strategy lines completor class provides functionality to correct and
 * complete invoice lines before sending them to Acumulus.
 *
 * This class:
 * - Adds vat rates to lines that need a strategy to compute their vat rates.
 *
 * @package Siel\Acumulus
 */
class CompletorStrategyLines
{
    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var array[] */
    protected $invoice;

    /** @var array[] */
    protected $invoiceLines;

    /** @var Source */
    protected $source;

    /**
     * The list of possible vat types, initially filled with possible vat types
     * type based on client country, invoiceHasLineWithVat(), is_company(), and
     * the digital services setting.
     *
     * @var int[]
     */
    protected $possibleVatTypes;

    /** @var array[] */
    protected $possibleVatRates;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(ConfigInterface $config, TranslatorInterface $translator)
    {
        $this->config = $config;
        $this->translator = $translator;
    }

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param array $invoice
     *   The invoice to complete.
     * @param Source $source
     *   The source object for which this invoice was created.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     *
     * @return array
     *   The completed invoice.
     */
    public function complete(array $invoice, Source $source, array $possibleVatTypes, array $possibleVatRates)
    {
        $this->invoice = $invoice;
        $this->invoiceLines = &$this->invoice['customer']['invoice']['line'];
        $this->source = $source;
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;

        $this->completeStrategyLines();
        return $this->invoice;
    }

    /**
     * Complete all lines that need a vat divide strategy to compute correct
     * values.
     */
    protected function completeStrategyLines()
    {
        if ($this->invoiceHasStrategyLine()) {
            $this->invoice['customer']['invoice'][Meta::StrategyCompletorInput]['vat-rates'] = str_replace(array(' ', "\r", "\n", "\t"), '', var_export($this->possibleVatRates, true));

            $isFirst = true;
            $strategies = $this->getStrategyClasses();
            foreach ($strategies as $strategyClass) {
                /** @var CompletorStrategyBase $strategy */
                $strategy = new $strategyClass($this->config, $this->translator, $this->invoice, $this->possibleVatTypes, $this->possibleVatRates, $this->source);
                if ($isFirst) {
                    $this->invoice['customer']['invoice'][Meta::StrategyCompletorInput]['vat-2-divide'] = $strategy->getVat2Divide();
                    $this->invoice['customer']['invoice'][Meta::StrategyCompletorInput]['vat-breakdown'] = str_replace(array(' ', "\r", "\n", "\t"), '', var_export($strategy->getVatBreakdown(), true));
                    $isFirst = false;
                }
                if ($strategy->apply()) {
                    $this->replaceLinesCompleted($strategy->getLinesCompleted(), $strategy->getReplacingLines(), $strategy->getName());
                    if (empty($this->invoice['customer']['invoice'][Meta::StrategyCompletorUsed])) {
                        $this->invoice['customer']['invoice'][Meta::StrategyCompletorUsed] = $strategy->getDescription();
                    } else {
                        $this->invoice['customer']['invoice'][Meta::StrategyCompletorUsed] .= '; ' . $strategy->getDescription();
                    }
                    // Allow for partial solutions: a strategy may correct only some of
                    // the strategy lines and leave the rest up to other strategies.
                    if (!$this->invoiceHasStrategyLine()) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Returns whether the invoice has lines that are to be completed using a tax
     * divide strategy.
     *
     * @return bool
     */
    public function invoiceHasStrategyLine()
    {
        $result = false;
        foreach ($this->invoiceLines as $line) {
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Strategy) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    /**
     * Returns a list of strategy class names.
     *
     * @return string[]
     */
    protected function getStrategyClasses()
    {
        $result = array();

        // For now hardcoded, but this can be turned into a discovery.
        $namespace = '\Siel\Acumulus\Invoice\CompletorStrategy';
        $result[] = "$namespace\\SplitKnownDiscountLine";
        $result[] = "$namespace\\SplitNonMatchingLine";
        $result[] = "$namespace\\ApplySameVatRate";
        $result[] = "$namespace\\TryAllVatRatePermutations";

        return $result;
    }

    /**
     * Replaces all completed strategy lines with the given completed lines.
     *
     * @param int[] $linesCompleted
     * @param array[] $completedLines
     *   An array of completed invoice lines to replace the strategy lines with.
     * @param string $strategyName
     */
    protected function replaceLinesCompleted(array $linesCompleted, array $completedLines, $strategyName)
    {
        // Remove old strategy lines that are now completed.
        $lines = array();
        foreach ($this->invoice['customer']['invoice']['line'] as $key => $line) {
            if (!in_array($key, $linesCompleted)) {
                $lines[] = $line;
            }
        }

        // And merge in the new completed ones.
        foreach ($completedLines as &$completedLine) {
            if ($completedLine[Meta::VatRateSource] === Creator::VatRateSource_Strategy) {
                $completedLine[Meta::VatRateSource] = Completor::VatRateSource_Strategy_Completed;
                $completedLine[Meta::StrategyCompletorUsed] = $strategyName;
            }
        }
        $this->invoice['customer']['invoice']['line'] = array_merge($lines, $completedLines);
    }
}
