<?php

/*
| Utilities / platform admin screens (Settings, Tasks, Tickets, Approvals, Templates, Comms, imports): user-facing
| wording by key. Controllers call __('utils.flash.key').
*/

return [

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('utils.flash.key').
    'flash' => [
        'technical_error' => 'A technical error stopped this action (reference :ref). Please try again, or quote the reference to IT support.',
        'settings_section_saved' => 'Settings saved (:count changed). They apply everywhere straight away.',
        'settings_nothing_changed' => 'Nothing changed.',
        'drafts_imported' => ':count draft(s) imported.',
        'import_errors' => 'Errors: :errors',
        'power_sheet_applied' => 'Power sheet applied.',
        'power_sheet_dry_run' => 'Dry run complete — nothing written.',
        'power_sheet_nothing_applied' => 'Nothing applied — fix the errors.',
        'queued_again' => 'Queued again as #:id.',
        'rejected_rows' => 'Rejected rows: :rows',
        'import_failed' => 'Import failed: :message',
        'draft_v_saved' => 'Draft v:version saved.',
        'file_not_template_export' => 'The file is not a template export.',
        'follow_up_saved' => 'Follow-up saved.',
        'import_completed' => 'Import Completed → :summary',
        'key_value_created_successfully' => 'Key Value created successfully!',
        'key_value_updated_successfully' => 'Key Value updated successfully!',
        'keyword_created_successfully' => 'Keyword created successfully!',
        'keyword_updated_successfully' => 'Keyword updated successfully!',
        'left_unchanged' => ':key left unchanged.',
        'remark_posted' => 'Remark posted.',
        'reset' => ':key reset.',
        'rule_removed_open_requests_keep_their' => 'Rule #:id removed (open requests keep their snapshot).',
        'rule_saved' => 'Rule #:rule saved.',
        'saved' => ':key saved.',
        'sheet_empty' => 'Sheet is empty.',
        'task_created' => 'Task created.',
        'task_deleted' => 'Task deleted.',
        'task_updated' => 'Task updated.',
        'ticket_moved' => 'Ticket moved to :status.',
        'ticket_opened' => 'Ticket :number opened.',
        'ticket_updated' => 'Ticket updated.',
        'topic_saved' => 'Topic saved.',
        'updated' => ':key updated.',
    ],

    // F1 help pane and Help centre (DEC-094, W16b).
    'help' => [
        'title' => 'Help',
        'centre' => 'Help centre',
        'missing' => 'Help for this screen has not been written yet. Use "Still need help?" if you are stuck.',
        'search' => 'Search help',
        'no_results' => 'No help article matches.',
        'updated' => 'Updated :date',
        'open_full' => 'Open as a page',
        'take_tour' => 'Take the tour',
        'coverage' => 'Screens with their own help: :covered of :total',
        'none_yet' => 'No help articles have been written yet.',
        'shortcut' => 'Press F1 (or ?) on any screen for its help.',
        'close' => 'Close help',
        'new' => 'help updated since you last read it',
        'tour_next' => 'Next',
        'tour_prev' => 'Back',
        'tour_done' => 'Done',
        'tour_empty' => 'None of the tour steps are on this page right now.',
    ],

    // "Still need help?" support requests (DEC-094, W16e).
    'support' => [
        'still_need_help' => 'Still need help?',
        'title' => 'Support request',
        'my_requests' => 'My tickets',
        'what' => 'What do you need?',
        'categories' => [
            'SUP_HOWTO' => 'How do I…?',
            'SUP_NOT_WORKING' => 'Something is not working',
            'SUP_WRONG_DATA' => 'Wrong data',
            'SUP_ACCESS' => 'Access or permission',
            'SUP_SUGGESTION' => 'Suggestion',
        ],
        'urgent' => 'Urgent — I cannot work',
        'subject' => 'Subject',
        'description' => 'Describe the problem',
        'diagnostics' => 'Attach diagnostics (this page, your recent actions and errors — never what you typed)',
        'screenshot' => 'Include this screenshot',
        'send' => 'Send',
        'cancel' => 'Cancel',
        'sending' => 'Sending…',
        'sent' => 'Support request :number sent. You can follow it under Utilities → Tickets.',
        'failed' => 'The request could not be sent. Please try again.',
        'invalid_category' => 'Choose what you need.',
        'owner_notification' => 'New support request :number',
        'not_executive' => 'Only support executives can be assigned.',
        'bundle_gone' => 'The diagnostics were deleted after the retention period.',
        'download_zip' => 'Download zip',
        'diagnostics' => 'Diagnostics',
        'team_only' => 'Visible to the support team only — not to the person who sent the request.',
        'no_diagnostics' => 'The request was sent without diagnostics.',
        'diagnostics_title' => 'Diagnostics — :number',
    ],

    // "Coming soon" page for menu items whose screen is not built yet (DEC-095 #8, D13).
    'coming_soon' => [
        'title' => 'Coming soon',
        'this_screen' => 'This screen',
        'text' => ':feature is not available yet. It is planned for a later release.',
        'back' => 'Back to the dashboard',
    ],

];
