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
            include_once('vendor/autoload.php');

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

                $data = \BawagCsvParser\BawagCsvParser::parse($content);

                saveData($data);
            }

            function applyRules() {
                global $pdo;

                $statement = $pdo->query('
                    SELECT 
                        id,
                        search,
                        category
                    FROM rules
                ');
                $result = $statement->fetchAll();

                $statement = $pdo->prepare('
                    UPDATE postingline
                    SET category=:category
                    WHERE text LIKE :search
                ');

                foreach ($result as $line) {
                    $line->search = '%' . $line->search . '%';
                    $statement->bindParam(':search', $line->search);
                    $statement->bindParam(':category', $line->category);
                    $statement->execute();
                }
            }

            function handleRulesForms() {
                if (isset($_POST['apply'])) {
                    applyRules();
                }

                if (!isset($_POST['search']) || !isset($_POST['category'])) {
                    return;
                }

                if (strlen(trim($_POST['search'])) === 0 || strlen(trim($_POST['category'])) === 0) {
                    return;
                }

                saveRule($_POST['search'], $_POST['category']);
            }

            function saveRule($search, $category) {
                global $pdo;

                $statement = $pdo->prepare('
                    INSERT IGNORE INTO rules
                    (
                        search,
                        category
                    )
                    VALUES (
                        :search,
                        :category
                    )
                ');

                $statement->bindParam(':search', $search);
                $statement->bindParam(':category', $category);
                $statement->execute();
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
                        contraAccountName,
                        category
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
                echo '<th>category</th>';
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
                    echo '<td>' . $line->category . '</td>';
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

            function showRules() {
                global $pdo;

                $statement = $pdo->query('
                    SELECT 
                        id,
                        search,
                        category
                    FROM rules
                ');
                $result = $statement->fetchAll();

                echo '<table>';
                echo '<tr><th>id</th><th>search</th><th>category</th><th>action</th></tr>';
                foreach ($result as $line) {
                    echo '<tr>';
                    echo '<td>' . $line->id . '</td>';
                    echo '<td>' . $line->search . '</td>';
                    echo '<td>' . $line->category . '</td>';
                    echo '<td>apply, delete</td>';
                    echo '</tr>';
                }
                echo '<tr><form action="index.php" method="post">';
                echo '<td></td>';
                echo '<td><input type="text" name="search" /></td>';
                echo '<td><input type="text" name="category" /></td>';
                echo '<td><button type="submit">Submit</button>, apply</td>';
                echo '</form></tr>';
                echo '<tr><form action="index.php" method="post">';
                echo '<td></td>';
                echo '<td></td>';
                echo '<td><input type="hidden" name="apply" value="true" /></td>';
                echo '<td><button type="submit">Apply All</button></td>';
                echo '</form></tr>';
                echo '</table>';
            }

            try {
                handleForms();
                handleRulesForms();

                echo '<h2>rules</h2>';
                showRules();

                echo '<h2>stats</h2>';
                //showStats();

                echo '<h2>lines</h2>';
                showLines();

            } catch (Exception $exception) {
                echo '<p class="expetion">Exception: ' . $exception->getMessage() . '</p>';
            }

            ?>
        </div>
    </body>
</html>
