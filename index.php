<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />

        <title>personal-accounting-system</title>

        <link rel="stylesheet" href="./css/normalize.css">
        <link rel="stylesheet" href="./css/style.css">

        <script src="./js/jquery-2.1.3.min.js"></script>
        <script src="./js/jquery.flot.js"></script>
        <script src="./js/jquery.flot.time.js"></script>
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

            <?php

            include_once('config.php');
            include_once('IsoCodeInterface.php');
            include_once('Iban.php');
            include_once('SwiftBic.php');

            function parseImport($content) {
                $lines = explode(PHP_EOL, $content);
                $data = array();
                foreach ($lines as $line) {
                    if (strlen($line) === 0) {
                        continue;
                    }
                    //$line = str_replace(["\n", "\t", "\r"],['', '', ''], $line);
                    $csvLine = str_getcsv($line, ';');

                    $date = new DateTime($csvLine[2]);

                    // remove leading plus sign
                    $csvLine[4] = ltrim($csvLine[4], '+$');
                    // remove dot, replace comma by dot.
                    $csvLine[4] = str_replace('.', '', $csvLine[4]);
                    $csvLine[4] = str_replace(',', '.', $csvLine[4]);

                    $csvLine[6] = '';
                    $csvLine[7] = '';
                    $csvLine[8] = '';
                    $csvLine[9] = '';
                    $csvLine[10] = '';
                    $csvLine[11] = '';

                    // todo: check https://github.com/PPOE/pgacc/blob/master/import.php line 185


                    // match id
                    $id = '';
                    $result = preg_match("@[A-Z]{2}/\d{9} @", $csvLine[1], $matches);
                    if ($result === 1) {
                        $id = trim($matches[0]);
                    }
                    $csvLine[7] = $id;

                    // match iban
                    // only matches DE and AT iban's for now.
                    $iban = '';
                    $result = preg_match("/ [A-Z]{2}\d{16,18} /", $csvLine[1], $matches);
                    if ($result === 1 && \IsoCodes\Iban::validate(trim($matches[0]))) {
                        $iban = trim($matches[0]);
                    } else {
                        if ($date->format('Y') < 2014) {
                            $result = preg_match("/ \d{11} /", $csvLine[1], $matches);
                            if ($result === 1) {
                                $iban = trim($matches[0]);
                            }
                        }
                    }
                    $csvLine[10] = $iban;

                    // before id
                    if (strlen($id)> 0) {
                        $split = explode($id, $csvLine[1], 2);
                        if (isset($split[0])) {
                            $csvLine[6] = trim($split[0]);
                        }
                    }

                    // after id and before iban
                    if (isset($split[1]) && strlen($iban) > 0) {
                        $split2 = explode($iban, $split[1], 2);
                        if (isset($split2[0])) {
                            $csvLine[8] = trim($split2[0]);
                            $result = preg_match("/ ([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})? /", $split2[0], $matches);
                            $result2 = preg_match("/ ([0-9]){5} /", $split2[0], $matches2);
                            if ($result === 1 && \IsoCodes\SwiftBic::validate(trim($matches[0]))) {
                                $csvLine[9] = trim($matches[0]);
                            } else if ($result2 === 1 && $date->format('Y') < 2014) {
                                $csvLine[9] = trim($matches2[0]);
                            }
                        }
                    }

                    // after iban
                    if (strlen($iban) > 0) {
                        $split = explode($iban, $csvLine[1], 2);
                        if (isset($split[1])) {
                            $csvLine[11] = trim($split[1]);
                        }
                    }

                    // extract name from before bankleitzahl
                    if (strlen($csvLine[11]) === 0 && $csvLine[8] && $csvLine[9] && $date->format('Y') < 2014) {
                        $split = explode($csvLine[9], $csvLine[8], 2);
                        if (isset($split[0])) {
                            $csvLine[11] = trim($split[0]);
                        }
                    }

                    // remove duplicate text
                    if ($csvLine[6] && $csvLine[11]) {
                        $csvLine[11] = trim(str_replace($csvLine[6], '', $csvLine[11]));
                    }

                    $data[] = $csvLine;
                }

                return $data;
            }

            function saveData($data) {
                global $pdo;

                $statement = $pdo->prepare('
                    INSERT IGNORE INTO postingline
                    (
                        postingLineId,
                        account,
                        text,
                        postingDate,
                        valueDate,
                        amount,
                        currency,
                        comment,
                        contraAccount,
                        contraAccountBic,
                        contraAccountName
                    )
                    VALUES (
                        :postingLineId,
                        :account,
                        :text,
                        STR_TO_DATE(:postingDate, \'%d.%m.%Y\'),
                        STR_TO_DATE(:valueDate, \'%d.%m.%Y\'),
                        :amount,
                        :currency,
                        :comment,
                        :contraAccount,
                        :contraAccountBic,
                        :contraAccountName
                    )
                ');

                foreach ($data as $line) {
                    $statement->bindParam(':postingLineId', $line[7]);
                    $statement->bindParam(':account', $line[0]);
                    $statement->bindParam(':text', $line[1]);
                    $statement->bindParam(':postingDate', $line[3]);
                    $statement->bindParam(':valueDate', $line[2]);
                    $statement->bindParam(':amount', $line[4]);
                    $statement->bindParam(':currency', $line[5]);
                    $statement->bindParam(':comment', $line[6]);
                    $statement->bindParam(':contraAccount', $line[9]);
                    $statement->bindParam(':contraAccountBic', $line[10]);
                    $statement->bindParam(':contraAccountName', $line[11]);
                    $statement->execute();
                }
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

                $data = parseImport($content);

                saveData($data);
            }

            function showLines() {
                global $pdo;

                $statement = $pdo->query('
                    SELECT 
                        id,
                        postingLineId,
                        account,
                        text,
                        postingDate,
                        valueDate,
                        date_format(valueDate, \'%d.%m.%Y\') as valueDate,
                        amount,
                        currency,
                        comment,
                        contraAccount,
                        contraAccountBic,
                        contraAccountName
                    FROM postingline
                ');
                $result = $statement->fetchAll();

                /*echo '<pre>';
                print_r($statement->fetchAll());
                echo '</pre>';*/

                echo '<table>';
                echo '<tr>';
                echo '<th>id</th>';
                //echo '<th>postingLineId</th>';
                //echo '<th>account</th>';
                echo '<th>valueDate</th>';
                echo '<th>comment</th>';
                echo '<th>contraAccount</th>';
                echo '<th>contraAccountBic</th>';
                echo '<th>contraAccountName</th>';
                echo '<th>amount</th>';
                echo '</tr>';

                foreach ($result as $line) {
                    echo '<tr>';
                    echo '<td>' . $line->id . '</td>';
                    //echo '<td>' . $line->postingLineId . '</td>';
                    //echo '<td>' . $line->account . '</td>';
                    echo '<td>' . $line->valueDate . '</td>';
                    echo '<td>' . $line->comment . '</td>';
                    echo '<td>' . $line->contraAccount . '</td>';
                    echo '<td>' . $line->contraAccountBic . '</td>';
                    echo '<td>' . $line->contraAccountName . '</td>';
                    echo '<td style="text-align: right;">' . number_format($line->amount, 2) . '</td>';
                    echo '</tr>';
                }

                echo '</table>';
            }

            function showStats() {
                global $pdo;

                // total
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                ');
                $result = $statement->fetch();
                echo 'total sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'total sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'total sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";

                // previous year
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                    WHERE
                        YEAR(valueDate) = YEAR(CURDATE()) - 1 
                ');
                $result = $statement->fetch();
                echo 'previous year sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'previous year sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'previous year sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";

                // current year
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                    WHERE
                        YEAR(valueDate) = YEAR(CURDATE()) 
                ');
                $result = $statement->fetch();
                echo 'current year sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'current year sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'current year sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";

                // previous month
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                    WHERE
                        MONTH(valueDate) = MONTH(CURDATE()) - 1 
                ');
                $result = $statement->fetch();
                echo 'previous month sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'previous month sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'previous month sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";

                // current month
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                    WHERE
                        MONTH(valueDate) = MONTH(CURDATE()) 
                ');
                $result = $statement->fetch();
                echo 'current month sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'current month sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'current month sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";

                // previous week
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                    WHERE
                        WEEK(valueDate) = WEEK(CURDATE()) - 1 
                ');
                $result = $statement->fetch();
                echo 'previous week sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'previous week sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'previous week sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";

                // current week
                $statement = $pdo->query('
                    SELECT
                        SUM(amount) as sum,
                        SUM(CASE when amount > 0 then amount else 0 end) as sumPositive,
                        SUM(CASE when amount < 0 then amount else 0 end) as sumNegative
                    FROM postingline
                    WHERE
                        WEEK(valueDate) = WEEK(CURDATE()) 
                ');
                $result = $statement->fetch();
                echo 'current week sum: ' . number_format($result->sum, 2) . "<br />\n";
                echo 'current week sumPositive: ' . number_format($result->sumPositive, 2) . "<br />\n";
                echo 'current week sumNegative: ' . number_format($result->sumNegative, 2) . "<br />\n";
                echo "<br />\n";


                $statement = $pdo->query('
                    SELECT
                        valueDate * 1000 AS timestamp,
                        SUM(amount),
                        (
                            SELECT
                                SUM(amount)
                            FROM postingline
                            WHERE valueDate <= a.valueDate
                        ) AS runningtotal
                    FROM
                        postingline a 
                    GROUP BY
                        valueDate
                    ORDER BY valueDate;
                ');
                $result = $statement->fetchAll();
                /*foreach ($result as $line) {
                    echo $line->valueDate . ': ' . number_format($line->runningtotal, 2) . "<br />\n";
                }*/

                $array = array();
                $min = 0;
                foreach ($result as $line) {
                    $array[] = array($line->timestamp, $line->runningtotal);
                    $min = $line->runningtotal < $min ? $line->runningtotal : $min;
                }
                echo '<script>var values = ' . json_encode($array) . ';</script>' . "\n";
                echo '<div id="graph" style="width: 100%; height: 400px;"></div>';

                ?>

                <script>
                  var previousPoint = null;
                  $("#graph").bind("plothover", function(event, pos, item) {
                    if (item) {
                      if (previousPoint == item.dataIndex) {
                        return;
                      }
                      previousPoint = item.dataIndex;
                      $('#tooltip').remove();
                      var date = new Date(item.datapoint[0]);
                      var dateformatted = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear();
                    var value = item.datapoint[1] + '€';
                      $('<div id="tooltip">' + dateformatted + ': ' + value + '</div>').css({
                        position: 'absolute',
                        top: item.pageY + 20,
                        left: item.pageX - 60,
                        display: 'none',
                        padding: 8,
                        'background-color': 'rgba(255, 255, 255, 0.8)'
                      }).appendTo("body").fadeIn(400);
                    } else {
                      $('#tooltip').remove();
                      previousPoint = null;
                    }
                  });

                  $.plot(
                    "#graph",
                    [values], {
                      series: {
                        lines: {
                          show: true,
                          steps: true
                        },
                        points: {
                          show: true
                        }
                      },
                      grid: {hoverable: true, clickable: true},
                      xaxis: {
                        mode: "time", 
                        timeformat: "%d.%m.%Y"
                      },
                      yaxis: {
                        min: <?php echo $min * 1.1; ?>
                      }
                    }
                  );
                </script>




                <?php

            }

            try {
                handleForms();

                echo '<h2>rules</h2>';

                echo '<h2>stats</h2>';
                showStats();

                echo '<h2>lines</h2>';
                showLines();

            } catch (Exception $exception) {
                echo '<p class="expetion">Exception: ' . $exception->getMessage() . '</p>';
            }

            ?>
        </div>
    </body>
</html>
