<?php
/*
$row=[];
$places = [];
$row[]=['x'=>0,'y'=>0, 'r'=>0];




print_r($row);
foreach ($row as $key => $value){

            $tile=$value;

            // Places array creation
            $x = $tile['x'];
            $y = $tile['y'];
            $r = $tile['r'];

            $places[$x.'x'.$y.] = 0;
            $places_coords = array( ($x+1).'x'.$y, ($x-1).'x'.$y, $x.'x'.($y-1),$x.'x'.($y+1) );

            foreach( $places_coords as $coord )
            {
                if( ! isset( $places[ $coord ] ) )
                    $places[ $coord ] = 1;
            }

}

print_r($places);
*/

$x=0;
print(!$x."\n");
print($x."\n"."\n");

$x=1;
print(!$x."\n");
print($x."\n");

?>
