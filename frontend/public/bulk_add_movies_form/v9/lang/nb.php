<?php
return [
  'page.title' => 'Bulk add – Singles & Box sets (multi) + Discs + Bonus items',
  'page.h1' => 'Bulk-registrering',

  'lang.label' => 'Språk',
  'lang.en' => 'English',
  'lang.nb' => 'Norsk (Bokmål)',
  'btn.apply' => 'Bruk',

  'btn.help' => 'Hjelp',
  'btn.reset' => 'Nullstill',
  'btn.close' => 'Lukk',
  'btn.cancel' => 'Avbryt',
  'btn.add' => 'Legg til',
  'btn.add_row' => 'Ny rad',
  'btn.preview_singles' => 'Forhåndsvis singles',
  'btn.submit_singles' => 'Send singles',
  'btn.add_box_set' => 'Legg til box set',
  'btn.preview_box_sets' => 'Forhåndsvis box sets',
  'btn.submit_box_sets' => 'Send alle box sets',
  'btn.add_disc' => 'Legg til disc',
  'btn.save_discs' => 'Lagre discer',
  'btn.add_item' => 'Legg til item',
  'btn.save_items' => 'Lagre items',
  'btn.edit' => 'Rediger',
  'btn.remove_boxset' => 'Fjern denne boksen',

  'btn.discs' => 'Discer',
  'btn.discs_count' => 'Discer ({n})',
  'btn.edit_count' => 'Rediger ({n})',

  // help (HTML allowed)
  'help.li1_html' => '<strong>Single releases</strong>: legg til mange produkter; hver kan ha flere discer.',
  'help.li2_html' => '<strong>Box sets</strong>: legg til flere box sets; hver boks har filmer i rekkefølge og discer; discer kan ha bonus-items.',
  'help.li3_html' => '<strong>Nøstede modaler</strong>: når Bonus-items er åpen blir Discs-modalen deaktivert for å hindre feilklikk.',
  'help.li4_html' => 'Språkbytte laster siden på nytt, men skjemaet lagres lokalt i nettleseren.',

  'tab.singles' => 'Single releases',
  'tab.boxsets' => 'Box sets (multi)',

  'label.format' => 'Format',
  'label.default_copy_count' => 'Standard antall kopier',
  'label.quick_add_title' => 'Hurtig-legg til (tittel)',
  'label.box_barcode' => 'Box-set strekkode (EAN-13)',
  'label.copy_count' => 'Antall kopier',
  'label.disc_type' => 'Disc-type',
  'label.disc_format' => 'Disc-format',
  'label.related_movie' => 'Relaterer til film (valgfritt)',
  'label.create_single' => 'Opprett single',
  'label.label' => 'Label',

  'format.dvd' => 'DVD',
  'format.bluray' => 'Blu-ray',
  'format.uhd' => '4K UHD',

  'section.titles' => 'Titler',
  'section.boxsets' => 'Box sets',
  'section.movies_in_box' => 'Filmer i boksen (rekkefølge)',
  'section.discs_in_box' => 'Discer i denne boksen (for copy_id = 1)',

  'col.title' => 'Tittel',
  'col.barcode' => 'Strekkode (EAN-13)',
  'col.imdb' => 'IMDb ID (valgfritt)',
  'col.discs' => 'Discer',
  'col.type' => 'Type',
  'col.format' => 'Format',
  'col.label' => 'Label (valgfritt)',
  'col.bonus_items' => 'Bonus items…',
  'col.seq' => 'Seq',
  'col.runtime' => 'Lengde (sek)',
  'col.notes' => 'Notater',
  'col.order' => 'Nr',
  'col.inner_ean' => 'Inner-case EAN',
  'col.treat_as_single' => 'Som single?',
  'col.related_movie' => 'Relatert film',

  'ph.title' => 'Tittel',
  'ph.ean13' => 'EAN-13',
  'ph.imdb' => 'tt1234567',
  'ph.runtime' => 'f.eks. 600',
  'ph.optional' => 'valgfritt',
  'ph.optional_label' => 'valgfri label',
  'ph.quick_add_title' => 'f.eks. Hidalgo',
  'ph.box_barcode' => '13 siffer',

  // hints (HTML allowed)
  'help.quick_add' => 'Legger til en ny tom rad med tittelen utfylt.',
  'hint.discs_html' => 'Bruk <strong>Discer</strong> for å legge inn feature/bonus-discer og redigere bonus-items per disc.',
  'hint.boxsets' => 'Legg til flere box sets, og forhåndsvis alt samlet.',
  'hint.related_movie' => 'Tom = bonus for hele boksen.',

  'modal.paste.title' => 'Lim inn filmliste',
  'modal.paste.label' => 'Én film per linje',
  'modal.paste.placeholder' => "Ringens brorskap\nTo tårn\nAtter en konge",

  'modal.discs.title' => 'Discer for single release',
  'modal.discs.hint_html' => 'Legg til <span class="mono">feature</span> og/eller <span class="mono">bonus</span>-discer. Bonus-items kan redigeres per disc.',

  'modal.bonus.title' => 'Bonus items',
  'modal.bonus.warn_not_bonus_html' => 'Denne discen er ikke merket som <span class="mono">bonus</span>. Du kan likevel legge inn items.',
  'modal.bonus.stored_as_html' => 'Lagres som: <span class="mono">(disc_id, seq_no)</span> i <span class="mono">disc_bonus_item</span>.',

  'modal.preview.title' => 'Forhåndsvis payload',

  // dynamic strings / summaries
  'fmt.box_set_n' => 'Box set #{n}',
  'fmt.untitled_n' => '(uten tittel #{n})',
  'fmt.movies_count' => '{n} filmer',
  'fmt.discs_count' => '{n} discer',
  'word.whole_box' => '(hele boksen)',
  'word.whole_box_plain' => 'hele boksen',
  'word.not_set' => 'Ikke satt',
  'word.no_ean' => 'Ingen EAN',
  'word.untitled' => '(uten tittel)',
  'word.no_barcode' => '(ingen strekkode)',

  'subtitle.single_disc' => 'Single disc · type={discType} · format={discFormat}',
  'subtitle.box_disc' => 'Box disc #{n} · type={discType} · format={discFormat} · relatert={rel}',

  'aria.remove_item' => 'Fjern item',
  'aria.remove_disc' => 'Fjern disc',
  'aria.remove_row' => 'Fjern rad',
  'aria.remove_movie' => 'Fjern film',

  // validation + reset
  'err.missing_feature_disc' => 'Hver single release må ha minst én FEATURE-disc. Mangler for:',
  'confirm.reset' => 'Nullstille alt? Dette vil tømme skjemaet og fjerne lagret utkast i denne nettleseren.',
];
