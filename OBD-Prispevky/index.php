<?php

/**
 * Plugin Name: OBD Příspěvky
 * Plugin URI: https://github.com/Boubik/OBD-Prispevky
 * Description: Plugin pro načítání XML dat a jejich zobrazení s filtrováním a řazením.
 * Version: 1.0.0
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
    if (isset($_POST['obd_templates_by_forma']) && is_array($_POST['obd_templates_by_forma'])) {
        update_option('obd_templates_by_forma', array_map('stripslashes', $_POST['obd_templates_by_forma']));
    }

    // Načtení hodnot
    $xml_data  = get_option('obd_xml_data', '');
?>
    <div class="wrap">
        <h1>OBD Příspěvky - Nastavení</h1>

        <form method="post">
            <h2>XML Data</h2>
            <p>Vložte celé XML, např. &lt;zaznamy&gt;&lt;zaznam id="123"&gt;...&lt;/zaznam&gt;&lt;/zaznamy&gt;.</p>
            <textarea name="obd_xml_data" rows="10" cols="100"><?php echo esc_textarea($xml_data); ?></textarea>

            <h2>Alternativní šablony podle literární formy</h2>
            <p>Pokud chcete použít jiné šablony pro různé typy záznamů (např. ČLÁNEK, MONOGRAFIE...), zadejte je zde.</p>
            <?php
            $templates_by_forma = get_option('obd_templates_by_forma', array());
            $form_types = ['ČLÁNEK', 'KONFERENČNÍ PŘÍSPĚVEK', 'MONOGRAFIE', 'VÝZKUMNÁ ZPRÁVA', 'PŘÍSPĚVEK VE SBORNÍKU', 'DEFAULT'];
            foreach ($form_types as $form) {
                $val = isset($templates_by_forma[$form]) ? esc_textarea($templates_by_forma[$form]) : '';
                echo "<h3>$form</h3>";
                echo "<textarea name=\"obd_templates_by_forma[$form]\" rows=\"4\" cols=\"100\">$val</textarea>";
            }
            ?>
            <p><strong>Poznámka:</strong> Pro správné přiřazení šablony musí záznam v XML obsahovat hodnotu v elementu <code>&lt;literarni_forma&gt;</code>. Například:
            <ul>
                <li>Pro článek: <code>&lt;literarni_forma&gt;ČLÁNEK&lt;/literarni_forma&gt;</code></li>
                <li>Pro monografii: <code>&lt;literarni_forma&gt;MONOGRAFIE&lt;/literarni_forma&gt;</code></li>
                <li>Pro konferenční příspěvek: <code>&lt;literarni_forma&gt;KONFERENČNÍ PŘÍSPĚVEK&lt;/literarni_forma&gt;</code></li>
            </ul>
            Pokud hodnota neodpovídá žádné definované šabloně, použije se šablona DEFAULT.
            </p>
            <h2>Jak použít shortcode</h2>
            <p>Vložte <code>[obd_prispevky]</code> do stránky/příspěvku. Volitelné parametry:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>limit="5"</code> – zobrazí prvních 5 záznamů. Pro nastavení offsetu a počtu záznamů použijte formát <code>limit="offset,počet"</code>, např. <code>limit="5,10"</code> znamená, že se přeskočí prvních 5 záznamů a zobrazí se následujících 10.</li>
                <li><code>sort="rok"</code> nebo <code>sort="autor"</code> nebo <code>sort="id"</code> (případně více, oddělených čárkou, např. <code>sort="rok,autor"</code>).</li>
                <li><code>order="asc"</code> nebo <code>order="desc"</code> (pokud je více polí ve <code>sort</code>, pak oddělených čárkou, např. <code>order="desc,asc"</code>).</li>
                <li><code>filter="pepa"</code> – vyhledá záznamy obsahující řetězec "pepa" (necitlivě na velikost písmen).</li>
                <li><code>filter_field="nazev"</code> – vyhledá pouze v konkrétním poli (např. "nazev", "autor" apod.). Pro vyhledávání ve všech polích použijte hodnotu "all".</li>
            </ul>
            <p>Příklad: <code>[obd_prispevky sort="rok,autor" order="desc,asc" limit="10,10" filter="pepa" filter_field="all"]</code></p>
            <?php submit_button('Uložit nastavení'); ?>
        </form>
    </div>
<?php
}

// Vytvoříme pole placeholderů pro jeden <zaznam>
function obd_build_placeholders($zaznam)
{
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
                $authors_better[] = '<span style="font-variant: small-caps;">' . (string)$autor->prijmeni . '</span>, ' . (string)$autor->jmeno;
            }
        }
    }
    $autori = implode(', ', $authors);
    $authori_better = implode(', ', $authors_better);

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

    $first_autor = isset($zaznam->autor_list->autor[0]) ? $zaznam->autor_list->autor[0] : null;
    $jmeno = $prijmeni = $titul_pred = $titul_za = '';
    if ($first_autor) {
        $jmeno = isset($first_autor->jmeno) ? (string)$first_autor->jmeno : '';
        $prijmeni = isset($first_autor->prijmeni) ? (string)$first_autor->prijmeni : '';
        $titul_pred = isset($first_autor->titul_pred) ? (string)$first_autor->titul_pred : '';
        $titul_za = isset($first_autor->titul_za) ? (string)$first_autor->titul_za : '';
    }

    // Sestavení placeholderu {urls} s URL z odkaz_list
    $urls = array();
    if (isset($zaznam->odkaz_list->odkaz)) {
        foreach ($zaznam->odkaz_list->odkaz as $odkaz) {
            $url_value = trim((string)$odkaz->url);
            if (!empty($url_value)) {
                $urls[] = '<a href="' . $url_value . '" target="_blank">' . $url_value . '</a>';
            }
        }
    }
    $urls_str = implode(', ', $urls);

    return array(
        '{id}'    => isset($zaznam['id']) ? (string)$zaznam['id'] : '',
        '{autor}' => $autori,
        '{nazev}' => $nazev,
        '{rok}'   => isset($zaznam->rok) ? (string)$zaznam->rok : '',
        '{issn}'  => isset($zaznam->issn) ? (string)$zaznam->issn : '',
        '{zdroj}' => isset($zaznam->zdroj_nazev) ? (string)$zaznam->zdroj_nazev : '',
        '{cislo}' => isset($zaznam->cislo)       ? (string)$zaznam->cislo       : '',
        '{jmeno}' => $jmeno,
        '{prijmeni}' => $prijmeni,
        '{titul_pred}' => $titul_pred,
        '{titul_za}' => $titul_za,
        '{misto}' => isset($zaznam->vydavatel_mesto) ? (string)$zaznam->vydavatel_mesto : '',
        '{nakladatel}' => isset($zaznam->vydavatel_nazev) ? (string)$zaznam->vydavatel_nazev : '',
        '{isbn}' => isset($zaznam->isbn) ? (string)$zaznam->isbn : '',
        '{autor_better}' => $authori_better,
        '{literarni_forma}' => isset($zaznam->literarni_forma) ? (string)$zaznam->literarni_forma : '',
        '{urls}' => $urls_str,
    );
}

// Nová funkce pro výběr šablony podle literární formy
// Tato funkce vybírá šablonu podle hodnoty <literarni_forma> v XML.
// Pokud je nastavena například hodnota "ČLÁNEK", očekává se, že v nastavení máte definovanou šablonu pro "ČLÁNEK".
// Pokud není nalezena, zkontroluje se existence šablony DEFAULT.
// Pokud ani ta není nastavena, použije se vestavěný fallback.
function obd_select_template_by_type($zaznam)
{
    $forma = isset($zaznam->literarni_forma) ? strtoupper(trim((string)$zaznam->literarni_forma)) : '';
    $templates_by_forma = get_option('obd_templates_by_forma', array());

    if (isset($templates_by_forma[$forma]) && !empty($templates_by_forma[$forma])) {
        return $templates_by_forma[$forma];
    }

    if (isset($templates_by_forma['DEFAULT']) && !empty($templates_by_forma['DEFAULT'])) {
        return $templates_by_forma['DEFAULT'];
    }

    return '<span style="font-variant: small-caps;">{prijmeni}</span>, {jmeno}. <em>{nazev}</em>. {misto}: {nakladatel}, {rok}. ISBN {isbn}.';
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
                $cmp = 0;
            }

            // Pokud je $cmp != 0, vyhodnotíme desc a vrátíme
            if ($cmp !== 0) {
                if ($ord === 'desc') {
                    $cmp = -$cmp;
                }
                return $cmp;
            }
        }
        // Pokud jsme došli až sem, znamená to, že všechny pole byly stejné => 0
        return 0;
    });
}

// Shortcode pro výpis
add_shortcode('obd_prispevky', 'obd_prispevky_shortcode');
function obd_prispevky_shortcode($atts)
{
    // Rozšíříme parametry shortcodu o filtrování
    $atts = shortcode_atts(array(
        'limit'        => -1,     // -1 = neomezeně
        'sort'         => '',     // např. "rok,autor"
        'order'        => 'asc',  // např. "desc,asc"
        'filter'       => '',     // vyhledávací výraz, např. "toy"
        'filter_field' => 'all',  // konkrétní pole, např. "nazev" nebo "autor". "all" znamená hledat ve všech polích
    ), $atts);

    // Načteme uložené XML a šablonu
    $xml_data = get_option('obd_xml_data', '');

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

    // Převedeme <zaznam> do pole
    $zaznamy = [];
    foreach ($xml->zaznam as $z) {
        $zaznamy[] = $z;
    }

    // Filtrace záznamů, pokud je nastaven vyhledávací výraz
    if (!empty($atts['filter'])) {
        $search = strtolower($atts['filter']);
        $field  = $atts['filter_field'];
        $zaznamy = array_values(array_filter($zaznamy, function ($z) use ($search, $field) {
            $placeholders = obd_build_placeholders($z);
            if ($field === 'all') {
                // Prohledáme všechna pole
                foreach ($placeholders as $value) {
                    if (stripos($value, $search) !== false) {
                        return true;
                    }
                }
                return false;
            } else {
                $key = '{' . $field . '}';
                return isset($placeholders[$key]) && stripos($placeholders[$key], $search) !== false;
            }
        }));
    }

    // Multi-level řazení
    obd_sort_records($zaznamy, $atts['sort'], $atts['order']);

    // Aplikace limitu: Podporujeme formát "offset,count" nebo "count"
    if (is_string($atts['limit']) && strpos($atts['limit'], ',') !== false) {
        $parts = array_map('trim', explode(',', $atts['limit']));
        $offset = (int)$parts[0];
        $count = isset($parts[1]) ? (int)$parts[1] : 0;
        if ($count > 0) {
            $zaznamy = array_slice($zaznamy, $offset, $count);
        }
    } else {
        $limit = (int)$atts['limit'];
        if ($limit > 0) {
            $zaznamy = array_slice($zaznamy, 0, $limit);
        }
    }

    // Výstup
    $output = '';
    foreach ($zaznamy as $zaznam) {
        $final_template = obd_select_template_by_type($zaznam);
        $output .= obd_parse_template($final_template, $zaznam);
    }

    return $output;
}
