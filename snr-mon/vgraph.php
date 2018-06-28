<?php
include_once './snrapp.php';
global $snrapp;

if(isset($_GET['sensor_id'])){
    $title =  isset($_GET['title']) ? $_GET['title'] : '';
    $subtitle =  isset($_GET['subtitle']) ? $_GET['subtitle'] : '';
    $color = isset($_GET['color']) ? $_GET['color'] : '#006699';
    $steps = isset($_GET['steps']) ? 'true' : 'false';
    $fill = isset($_GET['fillcolor']) ? 'true' : 'false';    
    $fillcolor = isset($_GET['fillcolor']) ? $_GET['fillcolor'] : 'null';
    echo $fill;
    $st = $snrapp->db->prepare('SELECT int_value, UNIX_TIMESTAMP(changed) as date, state FROM sensors_history WHERE (sensor_id = :sensor_id) AND (changed BETWEEN :date_from AND :date_to) ORDER BY changed');
    $st->bindValue(':sensor_id', $_GET['sensor_id'], PDO::PARAM_INT);
    $st->bindValue(':date_from', $_GET['date_from'], PDO::PARAM_STR);
    $st->bindValue(':date_to', $_GET['date_to'], PDO::PARAM_STR);
    if($st->execute()){
        $values = $st->fetchAll();
        // Построение графика
        $date_from = strtotime($_GET['date_from']);
        $date_to = strtotime($_GET['date_to']);
        $points = array(); // Точки серии
        $series = array(); // Серии
        $points_cnt = 1000; // Число точек на графике
        $interval = round(($date_to - $date_from) / $points_cnt);   
        $position = $date_to;
        $values_idx = count($values) - 1;
        while($position > $date_from) {            
            while ($values_idx >= 0) {
                //echo $position . ' <-------> ' . $values[$values_idx]['date'] . ' s=' . $values[$values_idx]['state']. '<br>';
                if($values[$values_idx]['date'] <= $position) {                    
                    if($values[$values_idx]['state'] >= 0) { // Определение разрыва графика
                        $points[] = array($position * 1000, $values[$values_idx]['int_value']);
                        break;
                    } else {                       
                        if(count($points) > 0) {
                            $gdata['data'] = array_reverse($points); // Добавляем серию при "обрыве"
                            $series[] = $gdata;                   
                            $points = array();                            
                        }
                        break;
                    }
                    
                } else {
                    $values_idx--;
                }
            }  
            $position = $position - $interval;
        }
        if(count($points) > 0) {
            $gdata['data'] = array_reverse($points); // Добавляем серию при "обрыве"
            //$gdata['lines'] = array('show' => true, 'fill' => 'true', 'fillColor' => '#000000');
            $series[] = $gdata;                             
        }
        //print_r($points);
        $series = array_reverse($series);
        $colors = array();
        foreach ($series as $value) {
            $colors[] = "'" . $color . "'";
        }
        $colors = implode(',', $colors);
    }
}

?>
<html>
  <head>
    <style type="text/css">
      body {
        margin: 0px;
        padding: 0px;
      }
      #container {
        width : 600px;
        height: 384px;
        margin: 8px auto;
      }
    </style>
  </head>
  <body>
      <div id="editor-render-0" style="
  width: 640px;
  height: 480px;
  margin: 24px auto;
"></div>
    <!--[if IE]>
    <script type="text/javascript" src="/static/lib/FlashCanvas/bin/flashcanvas.js"></script>
    <![endif]-->
    <script type="text/javascript" src="Flotr2-master/flotr2.min.js"></script>
    <script type="text/javascript">
    (function basic_time(container) {

    var
    d1 = <?php echo json_encode($series); ?>,
        options, graph, i, x, o;



    options = {
        title: '<?php echo $title;?>',
        subtitle: '<?php echo $subtitle;?>',
        shadowSize: 0,
        HtmlText: true,
        resolution: 1,
        fontSize: 7.5,
        fontColor: '#000000',
        colors: [<?php echo $colors;?>],
        lines: {
           show: true,
           lineWidth: 2,
           fill: <?php echo $fill;?>,
           fillBorder: false,
           fillColor: '<?php echo $fillcolor;?>',
           fillOpacity: 1,
           steps: <?php echo $steps;?>,
           stacked: false,
           },
        xaxis : {
            showLabels: true,
            showMinorLabels: false,
            labelsAngle: 0,
            titleAngle: '0',
            mode: 'time',
            timeFormat: 'DD.MM.YYYY'
           },
        yaxis : {
            showLabels: true,
            showMinorLabels: false,
            labelsAngle: 0,
            title: 'C',
            titleAngle: '90',
           },
        selection : {
            mode : 'x'
           },
    };

    // Draw graph with default options, overwriting with passed options


    function drawGraph(opts) {

        // Clone the options, so the 'options' variable always keeps intact.
        o = Flotr._.extend(Flotr._.clone(options), opts || {});

        // Return a new graph.
        return Flotr.draw(
        container, d1, o);
    }

    graph = drawGraph();

    Flotr.EventAdapter.observe(container, 'flotr:select', function(area) {
        // Draw selected area
        graph = drawGraph({
            xaxis: {
                min: area.x1,
                max: area.x2,
                mode: 'time',
            },
            yaxis: {

            }
        });
    });

    // When graph is clicked, draw the graph with default area.
    Flotr.EventAdapter.observe(container, 'flotr:click', function() {
        graph = drawGraph();
    });
})(document.getElementById("editor-render-0"));
    </script>
  </body>
</html>