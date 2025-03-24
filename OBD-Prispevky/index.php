<?php

/**
 * Plugin Name: OBD Příspěvky
 * Description: Plugin pro načítání XML (s <zaznam>), výpis podle jedné textové šablony, a vícenásobné řazení přes shortcode (např. sort="rok,autor" order="desc,asc").
 * Version: 1.0
 * Author: Boubik
 */

add_action('admin_menu', 'obd_prispevky_menu');
function obd_prispevky_menu()
{
    // Plugin se zobrazí pod "Nastavení" (Settings) v adminu
    add_options_page(
        'OBD Příspěvky',
        'OBD Příspěvky',
        'manage_options',
        'obd-prispevky',
        'obd_prispevky_settings_page'
    );
}

// Stránka nastavení pluginu – pouze XML a šablona
function obd_prispevky_settings_page()
{
    // Uložení z formuláře
    if (isset($_POST['obd_xml_data'])) {
        update_option('obd_xml_data', stripslashes($_POST['obd_xml_data']));
    }
    if (isset($_POST['obd_template'])) {
        update_option('obd_template', stripslashes($_POST['obd_template']));
    }

    // Načtení hodnot
    $xml_data  = get_option('obd_xml_data', '');
    $template  = get_option('obd_template', '');

?>
    <div class="wrap">
        <h1>OBD Příspěvky - Nastavení</h1>

        <form method="post">
            <h2>XML Data</h2>
            <p>Vložte celé XML, např. &lt;zaznamy&gt;&lt;zaznam id="123"&gt;...&lt;/zaznam&gt;&lt;/zaznamy&gt;.</p>
            <textarea name="obd_xml_data" rows="10" cols="100"><?php echo esc_textarea($xml_data); ?></textarea>

            <h2>Šablona výpisu (pseudo kód)</h2>
            <p>Zde definujte, jak se má každý &lt;zaznam&gt; zobrazit.
                Můžete používat HTML, &lt;br&gt;, a placeholdery {autor}, {nazev}, {rok}, {issn}, {zdroj}, {cislo}, {id} atd.</p>
            <textarea name="obd_template" rows="6" cols="100"><?php echo esc_textarea($template); ?></textarea>

            <h2>Jak použít shortcode</h2>
            <p>Vložte <code>[obd_prispevky]</code> do stránky/příspěvku. Volitelné parametry:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>limit="5"</code> – zobrazí max. 5 záznamů</li>
                <li><code>sort="rok"</code> nebo <code>sort="autor"</code> nebo <code>sort="id"</code> (či více, oddělených čárkou, např. <code>sort="rok,autor"</code>)</li>
                <li><code>order="asc"</code> nebo <code>order="desc"</code> (pokud je více polí v <code>sort</code>, pak je oddělte čárkou, např. <code>order="desc,asc"</code>)</li>
            </ul>
            <p>Příklad: <code>[obd_prispevky sort="rok,autor" order="desc,asc" limit="10"]</code></p>

            <?php submit_button('Uložit nastavení'); ?>
        </form>
    </div>
<?php
}

// Vytvoříme pole placeholderů pro jeden <zaznam>
function obd_build_placeholders($zaznam)
{
    // Atribut id z <zaznam id="...">
    $id = isset($zaznam['id']) ? (string)$zaznam['id'] : '';

    // Autoři
    $authors = array();
    if (isset($zaznam->autor_list->autor)) {
        foreach ($zaznam->autor_list->autor as $autor) {
            $fullName = '';
            if (!empty($autor->titul_pred)) {
                $fullName .= (string)$autor->titul_pred . ' ';
            }
            if (!empty($autor->jmeno)) {
                $fullName .= (string)$autor->jmeno . ' ';
            }
            if (!empty($autor->prijmeni)) {
                $fullName .= (string)$autor->prijmeni . ' ';
            }
            if (!empty($autor->titul_za)) {
                $fullName .= (string)$autor->titul_za;
            }
            $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
            if ($fullName !== '') {
                $authors[] = $fullName;
            }
        }
    }
    $autori = implode(', ', $authors);

    // Tituly
    $titles = array();
    if (isset($zaznam->titul_list->titul)) {
        foreach ($zaznam->titul_list->titul as $titul) {
            if (!empty($titul->nazev)) {
                $titles[] = (string)$titul->nazev;
            }
        }
    }
    $nazev = implode(' / ', $titles);

    // Rok, ISSN, zdroj, číslo
    $rok   = isset($zaznam->rok)         ? (string)$zaznam->rok         : '';
    $issn  = isset($zaznam->issn)        ? (string)$zaznam->issn        : '';
    $zdroj = isset($zaznam->zdroj_nazev) ? (string)$zaznam->zdroj_nazev : '';
    $cislo = isset($zaznam->cislo)       ? (string)$zaznam->cislo       : '';

    return array(
        '{id}'    => $id,
        '{autor}' => $autori,
        '{nazev}' => $nazev,
        '{rok}'   => $rok,
        '{issn}'  => $issn,
        '{zdroj}' => $zdroj,
        '{cislo}' => $cislo,
    );
}

// Nahradí placeholdery v šabloně
function obd_parse_template($template, $zaznam)
{
    $map = obd_build_placeholders($zaznam);
    return str_replace(array_keys($map), array_values($map), $template);
}

// Multi-level řazení podle 'sort' a 'order'
function obd_sort_records(&$zaznamy, $sort, $order)
{
    // Pokud není co řadit, return
    if (empty($sort)) {
        return;
    }
    // Rozdělíme sort a order podle čárek (může jich být více)
    $sortFields = array_map('trim', explode(',', $sort));
    $sortOrders = array_map('trim', explode(',', $order));

    usort($zaznamy, function ($a, $b) use ($sortFields, $sortOrders) {
        foreach ($sortFields as $index => $field) {
            // Zjistíme, jestli v order existuje odpovídající hodnota
            $ord = isset($sortOrders[$index]) ? strtolower($sortOrders[$index]) : 'asc';

            // Získáme hodnoty pro srovnání
            $cmp = 0;
            if ($field === 'autor') {
                // porovnání abecedně
                $mapA = obd_build_placeholders($a);
                $mapB = obd_build_placeholders($b);
                $cmp = strcmp($mapA['{autor}'], $mapB['{autor}']);
            } elseif ($field === 'rok') {
                // porovnání číselně
                $valA = (int)$a->rok;
                $valB = (int)$b->rok;
                $cmp = $valA <=> $valB;
            } elseif ($field === 'id') {
                // porovnání číselně podle atributu id
                $idA = (int)$a['id'];
                $idB = (int)$b['id'];
                $cmp = $idA <=> $idB;
            } else {
                // Pokud chcete další pole (např. 'cislo'), přidáte sem
                // Třeba:
                // if ($field === 'cislo') ...
                // Jinak 0 => nerozhoduje
                $cmp = 0;
            }

            // Pokud je $cmp != 0, vyhodnotíme desc a vrátíme
            if ($cmp !== 0) {
                if ($ord === 'desc') {
                    $cmp = -$cmp;
                }
                return $cmp;
            }
            // jinak pokračujeme dalším polem v $sortFields
        }
        // Pokud jsme došli až sem, znamená to, že všechny pole byly stejné => 0
        return 0;
    });
}

// Shortcode pro výpis
add_shortcode('obd_prispevky', 'obd_prispevky_shortcode');
function obd_prispevky_shortcode($atts)
{
    // Extend shortcode attributes with filter parameters
    $atts = shortcode_atts(array(
        'limit'        => -1,     // -1 = unlimited
        'sort'         => '',     // e.g., "rok,autor"
        'order'        => 'asc',  // e.g., "desc,asc"
        'filter'       => '',     // search term, e.g., "toy"
        'filter_field' => 'all',  // specific field name like "nazev" or "autor"; "all" to search everywhere
    ), $atts);

    // Load saved XML and template
    $xml_data = get_option('obd_xml_data', '');
    $template = get_option('obd_template', '');

    if (empty($xml_data)) {
        return '<p>Žádná XML data nejsou nastavena.</p>';
    }
    $xml = simplexml_load_string($xml_data);
    if (!$xml) {
        return '<p>Chyba při načítání XML. Zkontrolujte formát.</p>';
    }
    if (!isset($xml->zaznam)) {
        return '<p>V XML nebyly nalezeny žádné &lt;zaznam&gt; elementy.</p>';
    }

    // Convert <zaznam> elements to an array
    $zaznamy = [];
    foreach ($xml->zaznam as $z) {
        $zaznamy[] = $z;
    }

    // Filter records if a search term is provided
    if (!empty($atts['filter'])) {
        $search = strtolower($atts['filter']);
        $field  = $atts['filter_field'];
        $zaznamy = array_values(array_filter($zaznamy, function ($z) use ($search, $field) {
            $placeholders = obd_build_placeholders($z);
            if ($field === 'all') {
                // Check every field for the search term
                foreach ($placeholders as $value) {
                    if (stripos($value, $search) !== false) {
                        return true;
                    }
                }
                return false;
            } else {
                // Build the key name with curly braces (e.g., {nazev})
                $key = '{' . $field . '}';
                return isset($placeholders[$key]) && stripos($placeholders[$key], $search) !== false;
            }
        }));
    }

    // Multi-level sorting
    obd_sort_records($zaznamy, $atts['sort'], $atts['order']);

    // Apply limit if set
    if ($atts['limit'] > 0) {
        $zaznamy = array_slice($zaznamy, 0, $atts['limit']);
    }

    // Build output using the template for each record
    $output = '';
    foreach ($zaznamy as $zaznam) {
        $output .= obd_parse_template($template, $zaznam);
    }

    return $output;
}
