<?php
$wizard_summary_ui_texts = isset($wizard_summary_ui_texts) && is_array($wizard_summary_ui_texts)
    ? $wizard_summary_ui_texts
    : [
        'title' => 'Your package',
        'empty' => 'No services added yet.',
        'continue' => 'Continue to booking',
        'clear' => 'Clear',
        'more_suffix' => 'more',
        'estimated_total_prefix' => 'Estimated total: ',
        'service_prefix' => 'Service #',
        'offer_prefix' => 'Offer #',
        'preselected_medical_offer_prefix' => 'Preselected medical offer #',
        'destination_prefix' => 'Destination: ',
        'dates_prefix' => 'Dates: ',
        'not_available' => 'N/A',
    ];
?>
<div
    id="wizard-package-summary"
    class="package-summary d-none"
    data-empty-text="<?php echo htmlspecialchars($wizard_summary_ui_texts['empty'], ENT_QUOTES, 'UTF-8'); ?>"
    data-more-suffix="<?php echo htmlspecialchars($wizard_summary_ui_texts['more_suffix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-estimated-total-prefix="<?php echo htmlspecialchars($wizard_summary_ui_texts['estimated_total_prefix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-service-prefix="<?php echo htmlspecialchars($wizard_summary_ui_texts['service_prefix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-offer-prefix="<?php echo htmlspecialchars($wizard_summary_ui_texts['offer_prefix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-preselected-medical-offer-prefix="<?php echo htmlspecialchars($wizard_summary_ui_texts['preselected_medical_offer_prefix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-destination-prefix="<?php echo htmlspecialchars($wizard_summary_ui_texts['destination_prefix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-dates-prefix="<?php echo htmlspecialchars($wizard_summary_ui_texts['dates_prefix'], ENT_QUOTES, 'UTF-8'); ?>"
    data-not-available="<?php echo htmlspecialchars($wizard_summary_ui_texts['not_available'], ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <h5 class="mb-1"><?php echo htmlspecialchars($wizard_summary_ui_texts['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
            <div id="wizard-summary-list" class="small text-muted"><?php echo htmlspecialchars($wizard_summary_ui_texts['empty'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div id="wizard-summary-total" class="summary-total"></div>
        <div class="summary-actions d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary" id="wizard-summary-continue"><?php echo htmlspecialchars($wizard_summary_ui_texts['continue'], ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="btn btn-outline-primary" id="wizard-summary-clear"><?php echo htmlspecialchars($wizard_summary_ui_texts['clear'], ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </div>
</div>
