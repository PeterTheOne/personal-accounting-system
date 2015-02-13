<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />

        <title>personal-accounting-system</title>

        <link rel="stylesheet" href="./css/normalize.css">
        <link rel="stylesheet" href="./css/style.css">
    </head>
    <body>
        <div id="content">
            <h1>personal-accounting-system</h1>
            <h2>import</h2>

            <form id="import-text-form" action="index.php" method="post">
                <textarea name="import-text"></textarea>

                <button type="submit">Submit</button>
            </form>
            or
            <form id="import-file-form" enctype="multipart/form-data" action="index.php" method="post">
                <input type="file" name="import-file" /><br />

                <button type="submit">Submit</button>
            </form>

            <h2>rules</h2>

            <h2>lines</h2>

            <?php

            include_once('IsoCodeInterface.php');
            include_once('Iban.php');
            include_once('SwiftBic.php');

            function parseImport($content) {
                $lines = explode(PHP_EOL, $content);
                $array = array();
                foreach ($lines as $line) {
                    if (strlen($line) === 0) {
                        continue;
                    }
                    $line = str_replace(["\n", "\t", "\r"],['', '', ''], $line);
                    $csvLine = str_getcsv($line, ';');

                    // match types
                    /*$types = array(
                        'Bezahlung Maestro',
                        'Gutschrift Überweisung',
                        'Auszahlung Maestro',
                        'Abbuchung Einzugsermächtigung',
                        'Zinsen HABEN'
                    );
                    $foundType = '';
                    foreach($types as $type) {
                        if (mb_stripos($csvLine[1], $type) !== false) {
                            $foundType = $type;
                            break;
                        }
                    }
                    $csvLine[] = $foundType;*/

                    // split before and after id
                    $split = preg_split("@[A-Z]{2}/\d{9} @", $csvLine[1]);
                    if (isset($split[0])) {
                        $csvLine[] = trim($split[0]);
                    } else {
                        $csvLine[] = '';
                    }

                    // match id
                    $result = preg_match("@[A-Z]{2}/\d{9} @", $csvLine[1], $matches);
                    if ($result === 1) {
                        $csvLine[] = trim($matches[0]);
                    } else {
                        $csvLine[] = '';
                    }

                    // split after id and before iban
                    // also check if BIC or not
                    $split1 = preg_split("@[A-Z]{2}/\d{9} @", $csvLine[1]);
                    if (isset($split1[1])) {
                        $split2 = preg_split("/ [A-Z]{2}\d{16,18} /", $split1[1]);
                        if (isset($split2[0])) {
                            if (\IsoCodes\SwiftBic::validate(trim($split2[0]))) {
                                $csvLine[] = '';
                                $csvLine[] = trim($split2[0]);
                            } else {
                                $csvLine[] = trim($split2[0]);
                                $csvLine[] = '';
                            }
                        } else {
                            $csvLine[] = '';
                            $csvLine[] = '';
                        }
                    } else {
                        $csvLine[] = '';
                        $csvLine[] = '';
                    }

                    // match iban
                    // only matches DE and AT iban's for now.
                    $result = preg_match("/ [A-Z]{2}\d{16,18} /", $csvLine[1], $matches);
                    if ($result === 1 && \IsoCodes\Iban::validate(trim($matches[0]))) {
                        $csvLine[] = trim($matches[0]);
                    } else {
                        $date = new DateTime($csvLine[2]);
                        if ($date->format('Y') < 2014) {
                            $result = preg_match("/ \d{11} /", $csvLine[1], $matches);
                            if ($result === 1) {
                                $csvLine[] = trim($matches[0]);
                            } else {
                                $csvLine[] = '';
                            }
                        } else {
                            $csvLine[] = '';
                        }
                    }

                    // split after iban
                    // only matches DE and AT iban's for now.
                    $split = preg_split("/ [A-Z]{2}\d{16,18} /", $csvLine[1]);
                    if (isset($split[1])) {
                        $csvLine[] = trim($split[1]);
                    } else {
                        $csvLine[] = '';
                    }

                    // remove leading plus sign
                    $result = preg_replace("/^[\+]/", '', $csvLine[4]);
                    if ($result !== NULL) {
                        $csvLine[4] = $result;
                    }

                    $array[] = $csvLine;
                }

                echo '<pre>';
                print_r($array);
                echo '</pre>';
            }

            function handleForms() {
                $content = '';

                if (isset($_POST['import-text'])) {
                    $content = $_POST['import-text'];
                } else if (isset($_FILES['import-file'])) {
                    if ($_FILES['import-file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Failed file upload, Error ' . $_FILES['import-file']['error'] . '.');
                    }
                    $content = file_get_contents($_FILES['import-file']['tmp_name']);
                    if ($content === false) {
                        throw new Exception('Failed getting file content.');
                    }
                    $detectedEncoding = mb_detect_encoding($content, 'UTF-8, ISO-8859-15, ISO-8859-1', true);
                    $content = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);
                }

                if (strlen($content) === 0) {
                    return;
                }

                parseImport($content);
            }

            try {
                handleForms();
            } catch (Exception $exception) {
                echo '<p class="expetion">Exception: ' . $exception->getMessage() . '</p>';
            }




            ?>
        </div>
    </body>
</html>
