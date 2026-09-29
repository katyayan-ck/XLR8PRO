<?php

/**
 * Pricing module field labels — single source for validation names on the pricing process screens (DEC-073).
 */
return [

    'fields' => [
        'file' => 'Pricing workbook',
        'lists' => 'Price lists',
        'lists.*' => 'Price list',
        'wef_date' => 'WEF date',
        'hold_lists' => 'Hold lists',
        'hold_lists.*' => 'Hold list',
        'vehicle_info_file' => 'Vehicle Info workbook',
        'source' => 'Workbook',
        'reopen_lists' => 'Lists to reopen',
        'reopen_lists.*' => 'List to reopen',
        'action' => 'Action',
    ],

    'lists' => [
        'PV' => 'Price List PV',
        'CV' => 'Price List CV',
        'BEV' => 'Price List BEV',
        'LMM' => 'Price List LMM',
        'LMM_TZU' => 'Price List LMM TZU',
        'CSD' => 'Price List CSD (prices only)',
    ],

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('pricing.flash.key').
    'flash' => [
        'import_rules_first' => 'Import the :kinds rules first — none are stored.',
        'lists_on_hold' => 'On hold: :lists.',
        'lists_reopened' => 'Reopened: :lists.',
        'master_recalc_note' => 'Affected vehicles are recalculated in the background within a few minutes.',
        'master_row_removed' => ':label row removed.',
        'master_saved' => ':label saved.',
        'master_updated' => ':label updated.',
        'rules_import_started' => ':kind import started.',
        'detecting_new_vehicles' => ':message Detecting new vehicles…',
        'add_ons_discounts_done_insurance_rto' => 'Add-ons & discounts done — insurance & RTO next.',
        'add_ons_discounts_import_started' => 'Add-ons & discounts import started.',
        'add_ons_discounts_open_after_price' => 'Add-ons & discounts open after the price import.',
        'holds_are_set_at_hold_check' => 'Holds are set at the hold check.',
        'impact_reviewed_hold_or_reopen_lists' => 'Impact reviewed — hold or reopen lists, then calculate.',
        'impact_summary_opens_after_insurance_rto' => 'The impact summary opens after insurance & RTO.',
        'import_prices_first' => 'Import the prices first.',
        'import_queued_progress_shows_below' => 'Import queued — progress shows below.',
        'insurance_rto_open_after_add_ons' => 'Insurance & RTO open after add-ons & discounts.',
        'insurance_rto_ready_review_impact_summary' => 'Insurance & RTO ready — review the impact summary.',
        'no_add_ons_or_discounts_are' => 'No add-ons or discounts are stored yet — import the workbook first.',
        'no_open_pricing_process' => 'No open pricing process.',
        'no_open_pricing_process_discard' => 'No open pricing process to discard.',
        'price_import_opens_after_vehicle_info' => 'Price import opens after Vehicle Info.',
        'price_import_started' => 'Price import started.',
        'pricelist_put_on_hold' => 'Pricelist [:scope] has been put on Hold.',
        'pricelist_reopened' => 'Pricelist [:scope] has been Reopened.',
        'prices_are_already_published_in_process' => 'Prices are already published in this process.',
        'prices_are_already_published_vehicle_info' => 'Prices are already published — Vehicle Info can no longer change in this process.',
        'prices_imported_add_ons_discounts_next' => 'Prices imported — add-ons & discounts next.',
        'pricing_process_open_pricing_masters_are' => 'A Pricing Process is open — pricing masters are read-only until it completes or is discarded.',
        'pricing_process_still_open_resume_or' => 'Pricing process #:active is still open — resume or discard it first.',
        'process_not_at_add_ons_step' => 'The process is not at the add-ons step.',
        'process_not_at_impact_summary' => 'The process is not at the impact summary.',
        'process_not_at_insurance_rto_step' => 'The process is not at the insurance & RTO step.',
        'process_not_at_price_import_step' => 'The process is not at the price import step.',
        'process_not_at_vehicle_info_step' => 'The process is not at the Vehicle Info step.',
        'rto_rule_created_successfully' => 'RTO Rule created successfully.',
        'rto_rule_updated_successfully' => 'RTO Rule updated successfully.',
        'step_still_running_wait_it_finish' => 'A step is still running — wait for it to finish.',
        'tcs_configuration_updated_successfully' => 'TCS configuration updated successfully.',
        'vehicle_info_done_import_prices_next' => 'Vehicle Info done — import the prices next. Incomplete vehicles are skipped.',
        'vehicle_info_import_started' => 'Vehicle Info import started.',
        'vehicle_info_opens_after_detect_finished' => 'Vehicle Info opens after Detect has finished.',
    ],

];
