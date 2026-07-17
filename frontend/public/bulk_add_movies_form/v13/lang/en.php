<?php
return [
  'page.title' => 'Bulk add – Singles & Box sets (multi) + Discs + Bonus items',
  'page.h1' => 'Bulk add',

  'lang.label' => 'Language',
  'lang.en' => 'English',
  'lang.nb' => 'Norwegian (Bokmål)',
  'btn.apply' => 'Apply',

  'btn.help' => 'Help',
  'btn.reset' => 'Reset',
  'btn.close' => 'Close',
  'btn.cancel' => 'Cancel',
  'btn.add' => 'Add',
  'btn.add_row' => 'Add row',
  'btn.preview_singles' => 'Preview singles',
  'btn.submit_singles' => 'Submit singles',
  'btn.add_box_set' => 'Add box set',
  'btn.preview_box_sets' => 'Preview box sets',
  'btn.submit_box_sets' => 'Submit all box sets',
  'btn.add_disc' => 'Add disc',
  'btn.save_discs' => 'Save discs',
  'btn.add_item' => 'Add item',
  'btn.save_items' => 'Save items',
  'btn.edit' => 'Edit',
  'btn.remove_boxset' => 'Remove this box set',
  'btn.discs' => 'Discs',
  'btn.discs_count' => 'Discs ({n})',
  'btn.edit_count' => 'Edit ({n})',
  'btn.search_tmdb' => 'Search TMDB',

  // short format labels
  'format.dvd_short' => 'DVD',
  'format.bd_short' => 'BD',
  'format.uhd_short' => 'UHD',

  // disc type labels (values remain feature/bonus)
  'disc.feature' => 'feature',
  'disc.bonus' => 'bonus',

  // help (HTML allowed)
  'help.li1_html' => '<strong>Single releases</strong>: add many products; each can have multiple discs.',
  'help.li2_html' => '<strong>Box sets</strong>: add multiple box sets; each box has ordered movies and discs; discs can have bonus items.',
  'help.li3_html' => '<strong>Nested modals</strong>: when Bonus-items is open, the Discs modal becomes temporarily disabled to prevent misclicks.',
  'help.li4_html' => 'Language switching reloads the page, but your current form is saved locally in the browser.',

  'tab.singles' => 'Single releases',
  'tab.boxsets' => 'Box sets (multi)',

  'label.format' => 'Format',
  'label.default_copy_count' => 'Default copy count',
  'label.quick_add_title' => 'Quick add (title)',
  'label.box_barcode' => 'Box-set barcode (EAN-13)',
  'label.copy_count' => 'Copy count',
  'label.disc_type' => 'Disc type',
  'label.disc_format' => 'Disc format',
  'label.related_movie' => 'Relates to movie (optional)',
  'label.create_single' => 'Create single',
  'label.label' => 'Label',

  'section.titles' => 'Titles',
  'section.boxsets' => 'Box sets',
  'section.movies_in_box' => 'Movies in box (ordered)',
  'section.discs_in_box' => 'Discs in this box (for copy_id = 1)',

  'col.title' => 'Title',
  'col.barcode' => 'Barcode (EAN-13)',
  'col.imdb' => 'IMDb ID (optional)',
  'col.discs' => 'Discs',
  'col.type' => 'Type',
  'col.format' => 'Format',
  'col.label' => 'Label (optional)',
  'col.bonus_items' => 'Bonus items…',
  'col.seq' => 'Seq',
  'col.runtime' => 'Runtime (sec)',
  'col.notes' => 'Notes',
  'col.order' => 'Order',
  'col.inner_ean' => 'Inner-case EAN',
  'col.treat_as_single' => 'Treat as single?',
  'col.related_movie' => 'Related movie',

  'ph.title' => 'Title',
  'ph.ean13' => 'EAN-13',
  'ph.imdb' => 'tt1234567',
  'ph.runtime' => 'e.g. 600',
  'ph.optional' => 'optional',
  'ph.optional_label' => 'optional label',
  'ph.quick_add_title' => 'e.g. Hidalgo',
  'ph.box_barcode' => '13 digits',

  // hints (HTML allowed)
  'help.quick_add' => 'Adds a new empty row with the title filled in.',
  'hint.discs_html' => 'Use <strong>Discs</strong> to add feature/bonus discs and edit bonus tracks per disc.',
  'hint.boxsets' => 'Add multiple box sets, then preview all at once.',
  'hint.related_movie' => 'Empty = box-level bonus disc.',

  'modal.paste.title' => 'Paste movie list',
  'modal.paste.label' => 'One movie per line',
  'modal.paste.placeholder' => "Fellowship of the Ring\nThe Two Towers\nThe Return of the King",

  'modal.discs.title' => 'Discs for single release',
  'modal.discs.hint_html' => 'Add <span class="mono">feature</span> and/or <span class="mono">bonus</span> discs. Bonus items can be edited per disc.',

  'modal.bonus.title' => 'Bonus items',
  'modal.bonus.warn_not_bonus_html' => 'This disc is not marked as <span class="mono">bonus</span>. You can still add items.',
  'modal.bonus.stored_as_html' => 'Stored as: <span class="mono">(disc_id, seq_no)</span> in <span class="mono">disc_bonus_item</span>.',

  'modal.preview.title' => 'Preview payload',

  // dynamic strings / summaries
  'fmt.box_set_n' => 'Box set #{n}',
  'fmt.untitled_n' => '(untitled #{n})',
  'fmt.movies_count' => '{n} movies',
  'fmt.discs_count' => '{n} discs',
  'word.whole_box' => '(whole box)',
  'word.whole_box_plain' => 'whole box',
  'word.not_set' => 'Not set',
  'word.no_ean' => 'No EAN',
  'word.untitled' => '(untitled)',
  'word.no_barcode' => '(no barcode)',

  'subtitle.single_disc' => 'Single disc · type={discType} · format={discFormat}',
  'subtitle.box_disc' => 'Box disc #{n} · type={discType} · format={discFormat} · related={rel}',

  'aria.remove_item' => 'Remove item',
  'aria.remove_disc' => 'Remove disc',
  'aria.remove_row' => 'Remove row',
  'aria.remove_movie' => 'Remove movie',

  'err.missing_feature_disc' => 'Each single release must have at least one FEATURE disc. Missing for:',
  'confirm.reset' => 'Reset everything? This will clear the form and remove saved draft data from this browser.',
];
