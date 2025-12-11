<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * vizia implementation : © <Herve Dang> <dang.herve@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\vizia;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");


//first wheel  position
const FIRST_WHEEL = [
    0 => ['x' => 2 , 'y' => 1 ],
    1 => ['x' => 1 , 'y' => 1 ],
    2 => ['x' => 0 , 'y' => 1 ],
    3 => ['x' => 0 , 'y' => 0 ],
    4 => ['x' => 1 , 'y' => 0 ],
    5 => ['x' => 2 , 'y' => 0 ],
];

class Game extends \Table
{
    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "numberOfToken" => 10,
            "lastRoundAnnounced" => 11,
            "lastPlayer" => 12,
            "lowTile" => 13,

            //game option
            "multipleGame" =>100,
            "communalTiles" =>101,
            "groupBonus" =>102,
            "triangleBonus" =>103,
            "capture" =>104,
            "purchase" =>105,
            "teamPlay" =>106,
        ]);

        $this->translatedColors = [
            0 => clienttranslate('Blue'),
            1 => clienttranslate('Purple'),
            2 => clienttranslate('Red'),
            3 => clienttranslate('Orange'),
            4 => clienttranslate('Yellow'),
            5 => clienttranslate('Green'),
        ];

        $this->token =[];
        $this->captureToken =[];

    }

    /**
     *
     * Create tiles
     *
     */


    public function createTiles() {
        $sql = "INSERT INTO tile ( tile_color, tile_location) VALUES ";
        $values = [];

        $nbTile=$this->getGameStateValue('multipleGame')*12;

        $this->setGameStateValue('numberOfToken',$nbTile);

        foreach($this->translatedColors as $color_id => $color){
            for($value=1; $value<=$nbTile; $value++) {
                $values[] = "('".$color_id."', 'Deck')";
            }
        }

        $sql .= implode( ',', $values );
        static::DbQuery( $sql );

    }

    /**
     *
     * Draw personal tile for each player
     *
     */


    public function initiatePlayerHand() {
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            $tiles = $this->pickTile(2);
            static::DbQuery( "
            UPDATE tile set tile_location = 'Player', tile_location_arg = ".$player_id."
            WHERE tile_id = ".$tiles[0]." or tile_id = ".$tiles[1]);
        }
    }

    /**
     *
     * Draw tile for Common
     *
     */


    protected function commonTile(){
        $tileNbr=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Common'");

        $tileToPick=$this->getGameStateValue('communalTiles')-$tileNbr;

        $tiles = $this->pickTile($tileToPick);

        $sql="UPDATE tile SET tile_location = 'Common' where";

        $first=true;
        $tilePick = 0;
        foreach ( $tiles as $tile ) {
            if(!$first){
                $sql.="or";
            }else{
                $first=false;
            }
            $sql.= " tile_id = ".$tile." ";
            $tilePick++;
        }
        if($tilePick>0)
            static::DbQuery( $sql );

        $tileNbr=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Deck'");

        if($tileNbr<10){
            self::setGameStateValue("lowTile", 1);
            self::notifyAllPlayers( "lowTile", clienttranslate( 'Warning: less than 10 tiles remaining' ), array() );

        }
    }

    /**
     *
     * Initialise the First wheel
     *
     */


    public function createFirstWheel() {

        $sql = "INSERT INTO `token` ( `board_token_x`, `board_token_y` ) VALUES ('0', '0')";
        static::DbQuery( $sql );

        //create initial wheel
        foreach($this->translatedColors as $color_id => $color){
            $tile_id = $this->getUniqueValueFromDB("SELECT tile_id
            FROM tile WHERE
            tile_color='".$color_id."'ORDER BY RAND() LIMIT 0,1");

            $x=FIRST_WHEEL[$color_id]['x'];
            $y=FIRST_WHEEL[$color_id]['y'];

            static::DbQuery( "
            UPDATE tile SET board_tile_x = ".$x.", board_tile_y = ".$y.", tile_location = 'board'
            WHERE  tile_id = '".$tile_id."'");


            $sql=sprintf("INSERT IGNORE INTO tokenTile (token_id, tile_id) VALUES (1, %s)",
                $tile_id);

            static::DbQuery($sql);

        }

    }

    /**
     *
     * Pick n tile from the supply
     *
     */

    protected function pickTile( $number ){

        $tilesCollection = self::getCollectionFromDb( "SELECT tile_id id  FROM tile WHERE tile_location = 'Deck' ORDER BY RAND() LIMIT 0,".$number, false );

        $tiles = [];

        foreach ( $tilesCollection as $deck_tile_id => $tileId ) {
            $tiles[] = $tileId['id'];
        }

        return $tiles;
    }
    /**
     *
     * Check if two tile can be place neighbour
     *
     */

    public function CheckColor( $colorA, $colorB){

        if( $colorB == null){
            return true;
        }

        //+5 as if negative modulo with negative value do not work as we want
        if( ((($colorA+1)%6) == $colorB ) ||
            ((($colorA+5)%6) == $colorB )){
            return true;
        }

        return false;
    }

    /**
     *
     * Verify tile placement throw error if can be place here
     * check if left, right and under or over tile(s) permit to put our tile at the correct place
     * so if one of these adjacent did not permit throw an error
     * also thorw an erro if no adjacent tile
     *
     * These should be forbid by UI in the future
     *
     * @throws BgaUserException
     */

    public function CheckTilePlacement(int $id, int $x , int $y){
        $TileCurentColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE tile_id = ".$id);

        $TileLeftColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".($x-1)." and board_tile_y = ".$y);

        if(!$this->CheckColor($TileCurentColor,$TileLeftColor)){
            throw new \BgaUserException(self::_("Incorrect tile ".$x." ".$y." placement incorrect left color"), true);
        }
        $TileRightColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".($x+1)." and board_tile_y = ".$y);

        if(!$this->CheckColor($TileCurentColor,$TileRightColor))
            throw new \BgaUserException(self::_("Incorrect tile ".$x." ".$y." placement incorrect right color"), true);


        if( ( $x + $y ) %2 == 0){
            $TileUpColor=null;
            $TileDownColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".$x." and board_tile_y = ".($y+1));


            if(!$this->CheckColor($TileCurentColor,$TileDownColor)){
                throw new \BgaUserException(self::_("Incorrect tile ".$x." ".$y." placement incorrect down color"), true);
            }
        }else{
            $TileDownColor=null;
            $TileUpColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".$x." and board_tile_y = ".($y-1));

            if(!$this->CheckColor($TileCurentColor,$TileUpColor))
                throw new \BgaUserException(self::_("Incorrect tile ".$x." ".$y." placement incorrect up color"), true);

        }

        //test at least one color is defined
        //should not be usefull as it migth not be possible with the UI

        if ( ($TileLeftColor == null) &&
             ($TileRightColor == null) &&
             ($TileDownColor == null) && ($TileUpColor == null)){

            throw new \BgaUserException(self::_("Incorrect tile ".$x." ".$y." placement no adjacent tile"), true);
        }

    }

    /**
     *
     * Check if the wheel is completed
     *
     * execute capture "token" when enable
     *
     */

    public function CheckWheelCompleted( int $x1, int $x2, int $x3, int $y1, int $y2){

        $sql = "SELECT tile_id
            FROM tile WHERE
            (board_tile_x = ".$x1." and board_tile_y = ".$y1.") or
            (board_tile_x = ".$x2." and board_tile_y = ".$y1.") or
            (board_tile_x = ".$x3." and board_tile_y = ".$y1.") or
            (board_tile_x = ".$x1." and board_tile_y = ".$y2.") or
            (board_tile_x = ".$x2." and board_tile_y = ".$y2.") or
            (board_tile_x = ".$x3." and board_tile_y = ".$y2.")";

        $tiles=$this->getObjectListFromDB($sql,true);

        if(sizeof($tiles) == 6){
            $player_id = $this->getActivePlayerId();

            $x=min($x1, $x2, $x3);
            $y=min($y1, $y2);

            $sql=sprintf(
                "INSERT IGNORE INTO token (token_player, board_token_x, board_token_y,tmpToken)
                VALUES (%s, %s, %s, true)",
                $player_id,
                $x,
                $y,
            );

            $this->incStat(1,"WhellCompleted",$player_id);

            static::DbQuery($sql);

            $sql="SELECT token_id
                FROM token WHERE board_token_x = ".$x." and board_token_y = ".$y;

            $tokenId = $this->getUniqueValueFromDB($sql);
            foreach ($tiles as $tile){

                $sql=sprintf("INSERT IGNORE INTO tokenTile (token_id, tile_id) VALUES (%s, %s)",
                        $tokenId,
                        $tile);

                static::DbQuery($sql);

            }

            $this->token[]=[
                'id' => $tokenId,
                'player' => $player_id,
                'x' => $x,
                'y' => $y];

            if($this->getGameStateValue('capture') == 1){

                $this->CheckTokensCapture($x, $y);

            }

        }

    }

    /**
     *
     * Check if current tile finish the three possible wheel
     *
     * probably a better way to do it
     *
     */

    public function CheckWhellsCompleted( int $x , int $y){

        if( ( $x + $y ) %2 == 0){

            //first possible wheel
            $this->CheckWheelCompleted($x, ($x+1), ($x+2), $y, ($y+1));

            //second possible wheel
            $this->CheckWheelCompleted($x, ($x-1), ($x-2), $y, ($y+1));

            //third possible wheel
            $this->CheckWheelCompleted($x, ($x-1), ($x+1), $y, ($y-1));

        }else{

            //first possible wheel
            $this->CheckWheelCompleted($x, ($x+1), ($x+2), $y, ($y-1));

            //second possible wheel
            $this->CheckWheelCompleted($x, ($x-1), ($x-2), $y, ($y-1));

            //third possible wheel
            $this->CheckWheelCompleted($x, ($x-1), ($x+1), $y, ($y+1));
        }
    }

    /**
     *
     * Check if current token capture one of the six possible token
     *
     * probably a better way to do it
     *
     */


    public function CheckTokensCapture( int $x , int $y){
        $this->CheckTokenCapture(($x-1), ($y-1));
        $this->CheckTokenCapture(($x+1), ($y-1));
        $this->CheckTokenCapture(($x+2), ($y));
        $this->CheckTokenCapture(($x+1), ($y+1));
        $this->CheckTokenCapture(($x-1), ($y+1));
        $this->CheckTokenCapture(($x-2), ($y));
    }

    /**
     *
     * Check if a token can be captured
     *
     * ie all 6 adjacent token are present and it is alone (no sibling with the same color)
     * no token can also be capture
     *
     *
     */

    public function CheckTokenCapture( int $x , int $y){
        $token = $this->getObjectFromDB("SELECT token_player, token_id
        FROM token WHERE board_token_x = ".($x)." and board_token_y = ".($y));

        if($token != null){

            $tokenId=$token["token_id"];

            if ($token["token_player"] != null){
                $tokenPlayerTest="token_player = ".$token["token_player"];
                $tokenIdUI=$token["token_id"];
            }else{
                $tokenPlayerTest="token_player is null";
                $tokenIdUI=-1;
            }

            $adjacentTtokenNumber=(int)$this->getUniqueValueFromDB("SELECT count(token_id)
                FROM token WHERE
                (board_token_x = ".($x-1)." AND board_token_y = ".($y-1).") or
                (board_token_x = ".($x+1)." AND board_token_y = ".($y-1).") or
                (board_token_x = ".($x-2)." AND board_token_y = ".($y)."  ) or
                (board_token_x = ".($x+2)." AND board_token_y = ".($y)."  ) or
                (board_token_x = ".($x-1)." AND board_token_y = ".($y+1).") or
                (board_token_x = ".($x+1)." AND board_token_y = ".($y+1).")");

            if ($adjacentTtokenNumber == 6) {

                $sameColorTokenNumber=(int)$this->getUniqueValueFromDB("SELECT count(token_id)
                    FROM token WHERE
                    ".$tokenPlayerTest." AND (
                    (board_token_x = ".($x-1)." AND board_token_y = ".($y-1).") or
                    (board_token_x = ".($x+1)." AND board_token_y = ".($y-1).") or
                    (board_token_x = ".($x-2)." AND board_token_y = ".($y)."  ) or
                    (board_token_x = ".($x)."   AND board_token_y = ".($y)."   ) or
                    (board_token_x = ".($x+2)." AND board_token_y = ".($y)."  ) or
                    (board_token_x = ".($x-1)." AND board_token_y = ".($y+1).") or
                    (board_token_x = ".($x+1)." AND board_token_y = ".($y+1)."))");

                if ($sameColorTokenNumber == 1) {

                    $newPlayer=$this->getActivePlayerId();

                    $this->captureToken[]=[
                        'id' => $tokenIdUI,
                        'player' => $newPlayer,
                        'x' => $x,
                        'y' => $y];

                    $sql="UPDATE token SET token_player = ".$newPlayer.",
                            tmpToken = true
                            WHERE token_id = ".$tokenId;

                    $this->incStat(1,"TokenCaptured",$newPlayer);

                    static::DbQuery($sql);
                }

            }

        }else{
            $this->trace("NO token found");
        }
    }

    /**
     *
     * Check adjacent token is owned by the same player if yes set it to the same groupId
     *
     * probably a better way to do it
     *
     */

    public function CheckAdjacentToken( $token){
        $x=$token["x"];
        $y=$token["y"];

        $sql="SELECT token_id, tileGroup
            FROM token WHERE
            token_player = ".$token["token_player"]." and (
            (board_token_x = ".($x-1)." and board_token_y = ".($y-1).") or
            (board_token_x = ".($x+1)." and board_token_y = ".($y-1).") or
            (board_token_x = ".($x-2)." and board_token_y = ".($y).") or
            (board_token_x = ".($x)." and board_token_y = ".($y).") or
            (board_token_x = ".($x+2)." and board_token_y = ".($y).") or
            (board_token_x = ".($x-1)." and board_token_y = ".($y+1).") or
            (board_token_x = ".($x+1)." and board_token_y = ".($y+1)."))";

        $tokens = $this->getCollectionFromDb($sql);

        if  ($token["tileGroup"] == null) {
            $groupID=$token["id"];
        }else{
            $groupID=$token["tileGroup"];
        }

        foreach( $tokens as $token2 ){
            if  ($token2["tileGroup"] != null) {
                $sql="UPDATE token SET tileGroup = ".$groupID."
                    WHERE tileGroup = ".$token2["tileGroup"];

                static::DbQuery($sql);

                $groupID=$token2["tileGroup"];
            }else{
                $sql="UPDATE token SET tileGroup = ".$groupID."
                        WHERE token_id = ".$token2["token_id"];

                static::DbQuery($sql);
            }
        }

    }

    /**
     *
     * Check if player can play
     *
     * two version need to compare performance to know wich one to keep
     *
     */

    public function checkCanplay(): bool {
        $canPlay = false;

        $table_size=self::getObjectFromDB("SELECT
            max( board_tile_x ) as xMax,
            min( board_tile_x ) as xMin,
            max( board_tile_y ) as yMax,
            min( board_tile_y ) as yMin
            FROM tile WHERE tile_location = 'Board'",true);

        $xMin = $table_size["xMin"]-1;
        $xMax = $table_size["xMax"]+1;
        $yMin = $table_size["yMin"]-1;
        $yMax = $table_size["yMax"]+1;

        $tiles1 = self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
            FROM tile
            WHERE tile_location = 'Board'");

        for($i=$xMin;$i<=$xMax;$i++){
            for($j=$yMin;$j<=$yMax;$j++){
             $tilesOnBoard[$i][$j]=null;
            }
        }

        foreach ($tiles1 as $tile) {
            $x=$tile['x'];
            $y=$tile['y'];
            $tilesOnBoard[$x][$y]=$tile['color'];
        }

        $result["tiles"] = self::getCollectionFromDb("SELECT  tile_color
            FROM tile
            WHERE tile_location = 'common' OR
            (tile_location = 'Player' and tile_location_arg = ".$this->getActivePlayerId().")
            GROUP BY tile_color" );

        $x=$xMin;
        $y=$yMin;

        while ((!$canPlay) && ($x<=$xMax) && ($y<=$yMax))  {
            if($tilesOnBoard[$x][$y] == null){

                foreach ($result["tiles"] as $tile) {

                    $test1=false;
                    $test2=false;
                    $test3=false;
                    if (isset($tilesOnBoard[$x-1][$y]))
                        $test1=$this->CheckColor($tile["tile_color"] ,$tilesOnBoard[$x-1][$y]);

                    if (isset($tilesOnBoard[$x+1][$y]))
                        $test2=$this->CheckColor($tile["tile_color"] ,$tilesOnBoard[$x+1][$y]);

                    if( ( $x + $y ) %2 == 0){
                        if (isset($tilesOnBoard[$x][$y+1]))
                        $test3=$this->CheckColor($tile["tile_color"] ,$tilesOnBoard[$x][$y+1]);

                    }else{
                        if (isset($tilesOnBoard[$x][$y-1]))
                            $test3=$this->CheckColor($tile["tile_color"] ,$tilesOnBoard[$x][$y-1]);
                    }
                    if( $test1 || $test2 || $test3 )
                        $canPlay=true;

                }
            }

            $x++;
            if($x>$xMax){
                $x=$xMin;
                $y++;
            }

        }
        return $canPlay;
    }

    public function checkCanplay2(): bool {
        $canPlay = false;

        $places=$this->getPlaces(self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
                FROM tile
                WHERE tile_location = 'Board'"));

        $tiles = self::getCollectionFromDb("SELECT  tile_color
            FROM tile
            WHERE tile_location = 'common' OR
            (tile_location = 'Player' and tile_location_arg = ".$this->getActivePlayerId().")
            GROUP BY tile_color" );

        foreach ($places as $place) {
            $x=$place["x"];
            $y=$place["y"];

            $color1=self::getUniqueValueFromDB("
                SELECT tile_color
                FROM tile
                WHERE board_tile_x =".($x-1)." and board_tile_y =".$y);

            $color2=self::getUniqueValueFromDB("
                SELECT tile_color
                FROM tile
                WHERE board_tile_x =".($x+1)." and board_tile_y =".$y);

            if( ( $x + $y ) %2 == 0){
                $color3=self::getUniqueValueFromDB("
                    SELECT tile_color
                    FROM tile
                    WHERE board_tile_x =".$x." and board_tile_y =".($y+1));
            }else{
                $color3=self::getUniqueValueFromDB("
                    SELECT tile_color
                    FROM tile
                    WHERE board_tile_x =".$x." and board_tile_y =".($y-1));
            }

            foreach ($tiles as $tile) {
                    $test1=false;
                    $test2=false;
                    $test3=false;

                    if ($color1!=null){
                        $test1=$this->CheckColor($tile["tile_color"] ,$color1);
                    }
                    if ($color2!=null){
                        $test2=$this->CheckColor($tile["tile_color"] ,$color2);
                    }

                    if ($color3!=null){
                        $test3=$this->CheckColor($tile["tile_color"] ,$color3);
                    }

                    if( $test1 || $test2 || $test3 ){
                        $canPlay=true;
                        break;
                    }
            }
            if($canPlay)
                break;
        }

        return $canPlay;
    }

    /**
     *
     * create location where tile can be placed
     *
     */

    protected function getPlaces(array $tiles): array{
        $places=[];
        foreach ($tiles as $tile) {

            $x=$tile['x'];
            $y=$tile['y'];

            // Places array creation

            if( ( $x + $y ) %2 == 0){
                $places_coords = array( ($x+1).'x'.$y, ($x-1).'x'.$y, $x.'x'.($y+1) );
            }else{
                $places_coords = array( ($x+1).'x'.$y, ($x-1).'x'.$y, $x.'x'.($y-1) );
            }

            $places[$x.'x'.$y] = 0;

            foreach( $places_coords as $coord )
            {
                if( ! isset( $places[ $coord ] ) )
                    $places[ $coord ] = 1;
            }
        }

        $emptyPlaces = array();
        foreach( $places as $coord => $value )
        {
            if( $value == 1 )
            {
                $xpos = strpos( $coord, 'x' );

                $x = substr( $coord, 0, $xpos );
                $y = substr( $coord, $xpos+1 );
                $emptyPlaces[] = array( 'x' => $x, 'y' => $y ,'possibleColor' => $this->detectPossibleColor((int)$x,(int)$y));
            }
        }
        return $emptyPlaces;
    }

    protected function detectPossibleColor(int $x, int $y): array{
        $TileLeftColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".($x-1)." and board_tile_y = ".$y);

        if( $TileLeftColor == null){
            $possibleColor1=array(0,1,2,3,4,5);
        }else{
            $possibleColor1[0]= ($TileLeftColor+1)%6;
            $possibleColor1[1]= ($TileLeftColor+5)%6;
        }

        $TileRightColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".($x+1)." and board_tile_y = ".$y);

        if( $TileRightColor == null){
            $possibleColor2=array(0,1,2,3,4,5);
        }else{
            $possibleColor2[0]= ($TileRightColor+1)%6;
            $possibleColor2[1]= ($TileRightColor+5)%6;
        }


        if( ( $x + $y ) %2 == 0){
            $TileDownColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".$x." and board_tile_y = ".($y+1));

            if( $TileDownColor == null){
                $possibleColor3=array(0,1,2,3,4,5);
            }else{
                $possibleColor3[0]= ($TileDownColor+1)%6;
                $possibleColor3[1]= ($TileDownColor+5)%6;
            }
        }else{
            $TileUpColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE board_tile_x = ".$x." and board_tile_y = ".($y-1));
            if( $TileUpColor == null){
                $possibleColor3=array(0,1,2,3,4,5);
            }else{
                $possibleColor3[0]= ($TileUpColor+1)%6;
                $possibleColor3[1]= ($TileUpColor+5)%6;
            }
        }

        $result = array_values(array_intersect($possibleColor1, $possibleColor2, $possibleColor3));
/*
$this->dump("possibleColor1",$possibleColor1);
$this->dump("possibleColor2",$possibleColor2);
$this->dump("possibleColor3",$possibleColor3);
*/
$this->dump("result",$result);

        return $result;
    }



    /**
     *
     * Detect purchasable tile tile with only one adjacent tile
     *
     */

    public function getPurchasableTiles(): array {
        $purchasableTiles=[];
        if (self::getGameStateValue('purchase') ==1 ){
            $table_size=self::getObjectFromDB("SELECT
                max( board_tile_x ) as xMax,
                min( board_tile_x ) as xMin,
                max( board_tile_y ) as yMax,
                min( board_tile_y ) as yMin
                FROM tile WHERE tile_location = 'Board'",true);

            $result["tiles"] = self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
                FROM tile
                WHERE tile_location = 'Board'");

            $xMin = $table_size["xMin"]-1;
            $xMax = $table_size["xMax"]+1;
            $yMin = $table_size["yMin"]-1;
            $yMax = $table_size["yMax"]+1;

            for($i=$xMin;$i<=$xMax;$i++){
                for($j=$yMin;$j<=$yMax;$j++){
                 $tilesOnBoard[$i][$j]["empty"]=1;
                }
            }

            foreach ($result["tiles"] as $tile) {
                $x=$tile['x'];
                $y=$tile['y'];

                $tilesOnBoard[$x][$y]["empty"]=0;
                $tilesOnBoard[$x][$y]["id"]=$tile['id'];
            }
            $tilesOnBoardString="***************************\n";
            for($i=$xMin;$i<$xMax;$i++){
                $tilesOnBoardString.="\t\t".$i;
            }
             $tilesOnBoardString.="\n\n";

            for($j=$yMin;$j<=$yMax;$j++){
                $tilesOnBoardString.=$j."\t";
                for($i=$xMin;$i<=$xMax;$i++){
                    $tilesOnBoardString.="\t".$tilesOnBoard[$i][$j]["empty"];
                }
                $tilesOnBoardString.="\n";
            }

            foreach ($result["tiles"] as $tile) {
                $x=$tile['x'];
                $y=$tile['y'];
                $coord=$x." ".$y;

                if( ( $x + $y ) %2 == 0){

                    $resultat = $tilesOnBoard[($x+1)][$y]["empty"] +
                    $tilesOnBoard[($x-1)][$y]["empty"] +
                    $tilesOnBoard[$x][($y+1)]["empty"];

                }else{

                    $resultat = $tilesOnBoard[($x+1)][$y]["empty"] +
                    $tilesOnBoard[($x-1)][$y]["empty"] +
                    $tilesOnBoard[$x][($y-1)]["empty"];

                }

                if($resultat == 2)
                    $purchasableTiles[]=$tilesOnBoard[$x][$y]["id"];

            }

        }

        return $purchasableTiles;

    }

    /**
     * Player action token
     *
     * Player need to remove n token that he did not have real game he can only
     * place 12 token per game but this adaption place it automaticaly so to keep
     * to the rule we have to remove the excess
     *
     * @throws BgaUserException
     */

    public function actToken(string $token): void{
        $maxToken=(int)$this->getUniqueValueFromDB("SELECT count(token_id) as nb
            FROM `token`
            WHERE token_player = ".$this->getActivePlayerId()."
            GROUP BY token_player ");

        $tokenLimit=$this->getGameStateValue('numberOfToken');

        $tokenToRemove=$maxToken-$tokenLimit;

        $list_token = explode(';',rtrim($token,";"));

        $nbToken=sizeof($list_token);

        if($nbToken>$tokenToRemove){
            throw new \BgaUserException(self::_("Unexpected Error: you did not remove enought token"), true);
        }

        if($nbToken<$tokenToRemove){
            throw new \BgaUserException(self::_("Unexpected Error: you removed too much token"), true);
        }

        //explode token use remove last semi colon

        $removeToken=[];

        foreach ($list_token as $token){
            //token/tile data
            $data  = explode(',',$token);

            $removeToken[]=$data[0];

            //remove token
            self::DbQuery(sprintf("UPDATE token SET token_player = NULL  WHERE token_id = '%s'", $data[0]));

        }

        self::DbQuery("UPDATE token SET tmpToken = false  WHERE tmpToken = true");

        $this->notifyAllPlayers('removeToken',
            clienttranslate($this->getActivePlayerName()." remove token "),
            [
                'removeToken' => $removeToken,
            ]);

        $this->gamestate->nextState("nextPlayer");
    }

 public function actForcePass(): void{
        $this->gamestate->nextState("nextPlayer");
}

public function actDebugCheckTile(string $x, string $y): void
{


$this->debug("*******************************DEBUG place**********************************");
    $res=$this->detectPossibleColor((int)$x, (int)$y);
$msg="check tile x=".$x." y=".$y;
if(count($res) ==2)
    $msg.=" color1:".$res[0]." color2:".$res[1];
elseif(count($res) ==1)
    $msg.="color:".$res[0];
$this->debug($msg);
    $this->notifyAllPlayers('checkTile', //DEBUG
            $msg,
            [
                'res' => $res,
            ]);
}

    /**
     * Player action maint fnction
     *
     * Player can play tile from common area or private area it need to play at least one tile
     * if it private area is not full he can take some unplayed tile
     * tilePlayed -> tile player play
     * tilePlayer -> tile player take on his private area
     * tokenSpent -> token spend and tile bought advance rule
     *
     * @throws BgaUserException
     */

    public function actPlay(string $tilePlayed, string $tilePlayer, string $tokenSpent): void
    {

        if ($this->getActivePlayerId() !== $this->getCurrentPlayerId()) {
            throw new \BgaUserException(self::_("Unexpected Error: you are not the active player"), true);
        }

        $message='${player_name} play ';

        //need to play at leas one tile
        if(strlen($tilePlayed)==0){
                throw new \BgaUserException(self::_("You need to play at least one tile"), true);
        }

        //purchase tile
        if(strlen($tokenSpent)!=0){

            //check if game allow it
            if (self::getGameStateValue('purchase') ==0 ){
                throw new \BgaUserException(self::_("purchased tile is not permited for this game"), true);
            }

            //explode token use remove last semi colon
            $list_token = explode(';',rtrim($tokenSpent,";"));
            $tokenToRemove=[];
            $tileToRemove=[];

            foreach ($list_token as $token){
                //token/tile data
                $data  = explode(',',$token);

                //token to remove for other player
                $tokenToRemove[]="token_".$data[0];

                //tile to remove for other player
                $tileToRemove[]=$data[1];

                //remove token from player
                self::DbQuery(sprintf("UPDATE token SET token_player = NULL  WHERE token_id = '%s'", $data[0]));

                $this->incStat(1,"TilePurchased",$this->getActivePlayerId());
            }

        }

        //update tile location and get missing info
//REWORK FOR LESS DB ACCESS ??
        $list_playedTile = explode(';',rtrim($tilePlayed,";"));
        foreach ($list_playedTile as $tile){

            $tileData = explode(',',$tile);
            $tileId = (int)explode('_',$tileData[0])[1];
            $tiles[$tileId]["id"] = $tileId;
            $tiles[$tileId]["x"] = (int)$tileData[1];
            $tiles[$tileId]["y"] = (int)$tileData[2];
            $tileColor = $this->getUniqueValueFromDB("SELECT tile_color
            FROM tile WHERE tile_id = ".$tileId);

            $tiles[$tileId]["color"]=$tileColor;

            $this->CheckTilePlacement(
                $tileId,
                $tiles[$tileId]["x"],
                $tiles[$tileId]["y"]);

            $sql="UPDATE tile SET tile_location = 'Board',
                board_tile_x = ".$tiles[$tileId]["x"].",
                board_tile_y = ".$tiles[$tileId]["y"]."
                WHERE tile_id = ".$tileId;

            static::DbQuery($sql);

            $this->CheckWhellsCompleted( $tiles[$tileId]["x"], $tiles[$tileId]["y"]);


            $tilesPlayedUI[]=['id' => $tileColor, 'text' => $this->translatedColors[$tileColor] ];

//            $message.= $this->translatedColors[$tileColor]." tile ";

        }
        $message.='${tilesPlayedUI} ';

        //update private tile
        if(strlen($tilePlayer)!=0){
            $message.= "and take ";
            $list_playerTile = explode(';',rtrim($tilePlayer,";"));

            foreach ($list_playerTile as $tile){
                $tileId = explode('_',$tile)[1];

                if(array_key_exists($tileId,$tiles)){
                    throw new \BgaUserException(self::_("error tile both in player and played"), true);
                }

                $sql="UPDATE tile SET tile_location = 'Player',
                    tile_location_arg = ".$this->getActivePlayerId()."
                    WHERE tile_id = ".$tileId;
                static::DbQuery($sql);

                $tileColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE tile_id = ".$tileId);

                $tilesTakenUI[]=['id' => $tileColor, 'text' => $this->translatedColors[$tileColor] ];

//                $message.= $this->translatedColors[$tileColor]." ";

            }
            $message.='${tilesTakenUI} to his reserve';
        }else{
            $tilesTakenUI=Null;
        }


        //send removed token and tile
        if(strlen($tokenSpent)!=0){
            $this->notifyAllPlayers('purchased',
                clienttranslate("${player_name} bought tile "),
                [
                    'player_name' => $this->getActivePlayerName(),
                    'player_id' => $this->getActivePlayerId(),
                    'tileToRemove' => $tileToRemove,
                    'tokenToRemove' => $tokenToRemove,
                ]
                );
        }

        //send new tiles and places
        $this->notifyAllPlayers('playedTile',
            clienttranslate($message),
            [
                'player_name' => $this->getActivePlayerName(),
                'tilesPlayedUI' => $tilesPlayedUI,
                'tilesTakenUI' => $tilesTakenUI,
                'player_id' => $this->getActivePlayerId(),
                'tiles' => $tiles,
                'places' => $this->getPlaces($tiles),
            ]
        );

        //send possible purchase tile
        if (self::getGameStateValue('purchase') ==1 ){
            $purchasableTile=$this->getPurchasableTiles();

            $this->notifyAllPlayers('getPurchasableTiles',
                "",
                $purchasableTile);
        }

        //send new token
        if( count($this->token) != 0)
        $this->notifyAllPlayers('newToken',
            clienttranslate('${player_name} completed a wheel '),
            [
                'player_name' => $this->getActivePlayerName(),
                'token' => $this->token,
            ]
        );

        //send capture token
        if( count($this->captureToken) != 0)
            $this->notifyAllPlayers('captureToken',
                clienttranslate("${player_name} capture a token "),
                [
                    'player_name' => $this->getActivePlayerName(),
                    'token' => $this->captureToken,
                ]
            );


        // at the end of the action, move to the next state
        $this->gamestate->nextState("checkToken");
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * TO DO take into account token
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {

        $tileInDeck=$this->getUniqueValueFromDB("SELECT COUNT(*) FROM tile WHERE tile_location = 'Deck'");

        //do not use constante as we offer to use multiple game
        //but we could store instead in a global state but is use also DB so
        //probably no big improvement in term of performance as is also store in database
        $totalTile=$this->getUniqueValueFromDB("SELECT COUNT(*) FROM tile")-6;

        $progression = $tileInDeck/$totalTile*100;

        return $progression;

    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */

    public function stNextPlayer(): void {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        $player_data = self::loadPlayersBasicInfos();

        // Give some extra time to the active player when he completed an action
        $this->giveExtraTime($player_id);

        $this->activeNextPlayer();

        $this->commonTile();

        $common = self::getObjectListFromDB("SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Common'");

        $tilesRemain=$this->getObjectListFromDB("
            SELECT COUNT(tile_id)
            FROM tile
            WHERE tile_location = 'Deck'
            GROUP BY tile_color");

        $tilesNotPlayed=(int)$this->getUniqueValueFromDB("
            SELECT COUNT(tile_id)
            FROM tile
            WHERE tile_location = 'Deck' or tile_location = 'common' or tile_location = 'Player'");

        $this->notifyAllPlayers('nextPlayer',
            "",
            array(
                "commonTile" => $common,
                "tilesRemain" => $tilesRemain,
            )
        );

        if ($player_data[$player_id]['player_no'] == self::getGameStateValue('lastPlayer')){
            $this->incStat(1,"turns_number");
        }

        if( ($tilesNotPlayed == 0) ||
            ((self::getGameStateValue('lastRoundAnnounced') == 1) &&
            ($player_data[$player_id]['player_no'] == self::getGameStateValue('lastPlayer')))){

            $this->gamestate->nextState("calculateScore");

        }else{

            if(sizeof($common) == 0){
                $this->trace("need check player tile");

                do{
                    $player_id = (int)$this->getActivePlayerId();

                    $privateTileRemain = self::getUniqueValueFromDB("SELECT COUNT(tile_id)
                    FROM tile
                    WHERE tile_location = 'Player' and tile_location_arg = ".$player_id);

                    if($privateTileRemain == 0){
                        $this->notifyAllPlayers('passPlayer',
                            clienttranslate($this->getActivePlayerName()." did not have any tile left his turn is skipped"),
                            [
                            ]
                        );
                        $this->activeNextPlayer();

                    }
                }while ($privateTileRemain == 0);
            }
            $this->gamestate->nextState("checkCanPlay");
        }
    }


    /**
     *
     * Check token number to trigger end of game if necessary
     * and remove excess token state
     *
     */

    public function stCheckToken(): void{

        $maxToken=(int)$this->getUniqueValueFromDB("SELECT count(token_id) as nb
            FROM `token`
            WHERE token_player = ".$this->getActivePlayerId()."
            GROUP BY token_player ");

        $tokenLimit=$this->getGameStateValue('numberOfToken');
        if($tokenLimit==0){
            $sql="UPDATE token SET tmpToken = false
                WHERE tmpToken = true";

            static::DbQuery($sql);

            $this->gamestate->nextState("nextPlayer");
        }elseif($maxToken>$tokenLimit){
            self::notifyAllPlayers( "game_end_trigger", clienttranslate( 'Warning: The game will finish at the end of this round' ), array() );
            self::setGameStateValue("lastRoundAnnounced", 1);

            $tokenList = self::getObjectListFromDB("
                SELECT token_id id
                FROM token
                WHERE tmpToken= true");

            $this->notifyAllPlayers('tokenToRemove',"",
                [
                    'nbToken' => ($maxToken-$tokenLimit),
                    'token' => $tokenList,
                ]
                );

            $this->gamestate->nextState("chooseToken");

        }elseif ($maxToken==$tokenLimit){
            self::notifyAllPlayers( "game_end_trigger", clienttranslate( 'Warning: The game will finish at the end of this round' ), array() );
            self::setGameStateValue("lastRoundAnnounced", 1);
            $this->gamestate->nextState("nextPlayer");

            $sql="UPDATE token SET tmpToken = false
                WHERE tmpToken = true";

            static::DbQuery($sql);

        }else{
            $sql="UPDATE token SET tmpToken = false
                WHERE tmpToken = true";

            static::DbQuery($sql);

            $this->gamestate->nextState("nextPlayer");
        }
    }

    /**
     *
     * CheckPlay state: trigger skip player and reveal private card if necessary
     *
     */

    public function stCheckCanPlay(): void {

        if(!$this->checkCanplay()){

            $sql="UPDATE tile SET tile_location = 'Deck' where tile_location = 'common'";

            static::DbQuery($sql);

            $message=$this->getActivePlayerName().' can not play at all ';

            $privateTile= self::getObjectListFromDB("SELECT tile_color color
                FROM tile
                WHERE tile_location = 'Player' and tile_location_arg = ".$this->getActivePlayerId());

            if (sizeof($privateTile) == 0){
                $message.=" and do no not have any private tile";
            }elseif(sizeof($privateTile) == 1){
                $message.=" and has a";
                $message.=$this->translatedColors[$privateTile[0]['color']]." tile ";
                $message.=" in his personal tile";
            }elseif(sizeof($privateTile) == 2){
                $message.=" and has a ";
                $message.=$this->translatedColors[$privateTile[0]['color']]." tile and a ";
                $message.=$this->translatedColors[$privateTile[1]['color']]." tile";
                $message.=" in his personal tile";
            }else{
                //error
                $this->dump( "More than 2 private tile should not occured", $privateTile);
            }

            //send new tiles and places
            $this->notifyAllPlayers('canNotPlay',clienttranslate($message),
                [
                ]
            );

            $this->gamestate->nextState("nextPlayer");

        }else{
            $this->gamestate->nextState("playerTurn");
        }
     }


    /**
     *
     * Check if player canplay if not skip is turn and reveal his private tile
     *
     * Note: set triangle to 0 if not found might improve perfcomance need to be test/investigate
     *
     */

    public function checkTriangle($token) {
        if(!$token["triangleDown"]){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]-1));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]-1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                self::DbQuery(sprintf("UPDATE token SET triangleDown = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleDownLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleDownRight = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if(!$token["triangleUp"]){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]+1));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]+1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                self::DbQuery(sprintf("UPDATE token SET triangleUp = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleUpLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleUpRight = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if(!$token["triangleUpLeft"]){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]+2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]-1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                self::DbQuery(sprintf("UPDATE token SET triangleUpLeft = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleUpRight = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleUp = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if(!$token["triangleDownLeft"]){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]+2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]+1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                self::DbQuery(sprintf("UPDATE token SET triangleDownLeft = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleDownRight = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleDown = 1  WHERE token_id = '%s'", $token3));
            }
        }

      if(!$token["triangleUpRight"]){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]-2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]-1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                self::DbQuery(sprintf("UPDATE token SET triangleUpRight = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleUpLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleUp = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if(!$token["triangleDownRight"]){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]-2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                FROM token
                WHERE token_player =".$token["token_player"]." and
                board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]+1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                self::DbQuery(sprintf("UPDATE token SET triangleDownRight = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleDownLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleDown = 1  WHERE token_id = '%s'", $token3));
            }
        }

    }

    /**
     *
     * Calculate point
     *
     * might have a solution to improve calculation and ui show
     *
     * @throws BgaUserException
     */

    public function calculateScore($debug=false): void {

        $players = $this->loadPlayersBasicInfos();

        $simplePoint = [['str' => clienttranslate("point simple tile"), 'args' => []]];
        $bicolorPoint = [['str' => clienttranslate("point bicolor tile"), 'args' => []]];
        $prismePoint = [['str' => clienttranslate("point prisme tile"), 'args' => []]];

        $trianglePoint = [['str' => clienttranslate("triangle point"), 'args' => []]];
        $pointsGroup = [['str' => clienttranslate("points group"), 'args' => []]];
        $pointsTotal = [['str' => clienttranslate("points total"), 'args' => []]];

        $nameRow = [''];

        $i=0;
        $j=0;
        foreach ($players as $player_id => $player) {
            $simpleWheel[$player_id]=0;
            $bicolorWheel[$player_id]=0;
            $prismeWheel[$player_id]=0;
            if(($i%2)==0){
                $teamA[$j]=$player_id;
            }else{
                $teamB[$j]=$player_id;
                $j++;
            }
            $i++;
       }

        $tokens = self::getCollectionFromDb("
            SELECT token_id as id, token_player
            FROM token");

        foreach( $tokens as $token ){
            if($token['token_player'] != NULL){
                $colors = self::getCollectionFromDb("SELECT tile_color
                    FROM tile, tokenTile
                    WHERE tile.tile_id = tokenTile.tile_Id and
                        tokenTile.token_id = ".$token["id"]."
                        GROUP BY tile_color");
                // multicolor
                if (sizeof($colors) == 6){
                    $prismeWheel[$token["token_player"]]++;
                // bicolor
                }else if (sizeof($colors) == 2){
                     $bicolorWheel[$token["token_player"]]++;
                //simple can have 3 or 4 color
                }else if ((sizeof($colors) == 3) || (sizeof($colors) == 4)){
                     $simpleWheel[$token["token_player"]]++;
                //error
                }else{
                    $msg="nb color:".sizeof($colors)." token id ".$token["id"];
                    $this->dump( "Error calculation point error", $msg);
                }

                //always calculate  for stat
                $tokenUpdated = self::getCollectionFromDb("
                    SELECT token_id as id,token_player,
                    board_token_x as x , board_token_y as y,
                    triangleDown, triangleUpLeft, triangleDownLeft,
                    triangleUp, triangleDownRight, triangleUpRight
                    FROM token WHERE token_id = ".$token["id"]);

                $this->checkTriangle($tokenUpdated[$token["id"]]);

                $tokenUpdated = self::getCollectionFromDb("
                    SELECT token_id as id,token_player,
                    board_token_x as x , board_token_y as y, tileGroup
                    FROM token WHERE token_id = ".$token["id"]);

                $this->CheckAdjacentToken($tokenUpdated[$token["id"]]);
            }

        }

        if ($this->getGameStateValue('teamPlay') == 1){
            $nameRow[] = [
                'str' => '${player_name1} ${player_name2}',
                'args' => ['player_name1' => $this->getPlayerNameById($teamA[0]),
                           'player_name2' => $this->getPlayerNameById($teamA[1])
                ],
                'type' => 'header',
            ];

            $nameRow[] = [
                'str' => '${player_name1} ${player_name2}',
                'args' => ['player_name1' => $this->getPlayerNameById($teamB[0]),
                           'player_name2' => $this->getPlayerNameById($teamB[1])
                ],
                'type' => 'header',
            ];
        }

        $i=0;
        $j=0;
        foreach ($players as $player_id => $player) {
            if ($this->getGameStateValue('teamPlay') == 0){
                $nameRow[$player_id] = [
                    'str' => '${player_name}',
                    'args' => ['player_name' => $this->getPlayerNameById($player_id)],
                    'type' => 'header',
                ];
            }
            //can use directly result of checkTriangle but "work" only the first time as we did not count twice the triangle
            $triangeNumberTmp=(self::getUniqueValueFromDB("SELECT count(token_id)
                    FROM token
                    WHERE token_player =".$player_id." and
                    triangleDown = 1")+
                    self::getUniqueValueFromDB("SELECT count(token_id)
                    FROM token
                    WHERE token_player =".$player_id." and
                    triangleUp = 1"));
            $this->setStat($triangeNumberTmp,"Triangle",$player_id);

            if($this->getGameStateValue('triangleBonus') == 1){
                $triangeNumber[$player_id]=$triangeNumberTmp;

            }else{
                $triangeNumber[$player_id]=0;
            }

            $group=self::getUniqueValueFromDB("
                        SELECT max(count) FROM (
                            SELECT count(token_id) as count
                            FROM token
                            WHERE token_player =".$player_id."
                            GROUP BY tileGroup) as tmp");

            if($group == NULL)
                $group =0;

            $this->setStat($group,"Group",$player_id);


            $calculTotalTmp=
                $simpleWheel[$player_id]+
                $bicolorWheel[$player_id]*2+
                $prismeWheel[$player_id]*3+
                $triangeNumber[$player_id]*2+
                $group;


            if ($this->getGameStateValue('teamPlay') == 0){
                $prismePoint[$player_id] = $prismeWheel[$player_id]." x 3 = ".$prismeWheel[$player_id]*3;
                $bicolorPoint[$player_id] = $bicolorWheel[$player_id]." x 2 = ".$bicolorWheel[$player_id]*2;
                $simplePoint[$player_id] = $simpleWheel[$player_id];
                $trianglePoint[$player_id] = $triangeNumber[$player_id]." x 2 = ".$triangeNumber[$player_id]*2;
                $pointsTotal[$player_id]=$calculTotalTmp;

                if($this->getGameStateValue('groupBonus') == 1){
                    $pointsGroup[$player_id]=$group;
                }else{
                    $pointsGroup[$player_id]=0;
                }

                self::DbQuery(sprintf("UPDATE player SET player_score = %d WHERE player_id = '%s'", $calculTotalTmp, $player_id));

            }else{
                $indice=$i%2+1;
                if ( ($i == 0) || ($i == 1) ){
                    $prismeWheel[$indice] = $prismeWheel[$player_id];
                    $bicolorWheel[$indice] = $bicolorWheel[$player_id];
                    $simpleWheel[$indice] = $simpleWheel[$player_id];
                    $triangeNumber[$indice] = $triangeNumber[$player_id];
                    $totalTeam[$indice] = $calculTotalTmp;

                    $prismePoint[$indice] = "( ".$prismeWheel[$player_id]." + ";
                    $bicolorPoint[$indice] = "( ".$bicolorWheel[$player_id]." + ";
                    $simplePoint[$indice] = $simpleWheel[$player_id]." + ";
                    $trianglePoint[$indice] = "( ".$triangeNumber[$player_id]."  + ";
                    $pointsTotal[$indice] = $calculTotalTmp." + ";
                    if($this->getGameStateValue('groupBonus') == 1){
                        $pointsGroup[$indice]=$group." + ";
                        $groupTeam[$indice]=$group;
                    }else{
                        $pointsGroup[$indice]=0;
                    }
                    $playerTeam[$indice]=$player_id;

                }else{
                    $prismeWheel[$indice] += $prismeWheel[$player_id];
                    $bicolorWheel[$indice] += $bicolorWheel[$player_id];
                    $simpleWheel[$indice] += $simpleWheel[$player_id];
                    $triangeNumber[$indice] += $triangeNumber[$player_id];
                    $totalTeam[$indice] += $calculTotalTmp;

                    $prismePoint[$indice] .= $prismeWheel[$player_id]." ) x 3 = ".$prismeWheel[$indice]*3;
                    $bicolorPoint[$indice] .= $bicolorWheel[$player_id]." ) x 2 = ".$bicolorWheel[$indice]*2;
                    $simplePoint[$indice] .= $simpleWheel[$player_id]." = ".$simpleWheel[$indice];
                    $trianglePoint[$indice] .= $triangeNumber[$player_id]." ) x 2 = ".$triangeNumber[$indice]*2;
                    $pointsTotal[$indice].= $calculTotalTmp." = ".$totalTeam[$indice];
                    if($this->getGameStateValue('groupBonus') == 1){
                        $groupTeam[$indice]+=$group;
                        $pointsGroup[$indice].=$group." = ".$groupTeam[$indice];
                    }

                    self::DbQuery(sprintf(
                        "UPDATE player SET player_score = %d WHERE player_id = '%s' or player_id = '%s'",
                         $totalTeam[$indice],
                         $playerTeam[$indice],
                         $player_id));

                }
                $i++;
            }
            $this->setStat($prismeWheel[$player_id],"PrismeWheel",$player_id);
            $this->setStat($bicolorWheel[$player_id],"BicolorWheel",$player_id);
            $this->setStat($simpleWheel[$player_id],"SimpleWheel",$player_id);


            $this->setStat($calculTotalTmp,"Total",$player_id);

        }

        $table = [$nameRow,$simplePoint,$bicolorPoint,$prismePoint];
            if($this->getGameStateValue('triangleBonus') == 1){
                $table[] = $trianglePoint;
            }
            if($this->getGameStateValue('groupBonus') == 1){
                $table[] = $pointsGroup;
            }

        $table[]=$pointsTotal;

        $this->notifyAllPlayers("tableWindow", clienttranslate("End Scoring"), [
            "id" => 'finalScoring',
            "title" => "",
            "table" => $table,
            "closing" => clienttranslate("Close"),
        ]);

if(!$debug)
        $this->gamestate->nextState("endGame");
    }


    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $player_id = $this->getCurrentPlayerId();
        $result['player_id'] = $player_id;

        $result['purchase'] = self::getGameStateValue('purchase');

        $result["purchasableTiles"] = $this->getPurchasableTiles();

        $result['finalRound'] = self::getGameStateValue('lastRoundAnnounced');

        $result['lowTile'] = self::getGameStateValue('lowTile');

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        $result["hand"] = self::getObjectListFromDB("SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Player' and tile_location_arg = ".$player_id);

        $result["common"] = self::getObjectListFromDB("SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Common'");

        //table
        $result["tiles"] = self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
                FROM tile
                WHERE tile_location = 'Board'");

        $result["token"] = self::getObjectListFromDB("SELECT token_id id,board_token_x x,board_token_y y, token_player player, tileGroup
                FROM token");

        $result["tokenToDecide"] = self::getObjectListFromDB("SELECT token_id id
                FROM token
                WHERE tmpToken= true");


        $maxToken=(int)$this->getUniqueValueFromDB("SELECT count(token_id) as nb
            FROM `token`
            WHERE token_player = ".$this->getActivePlayerId()."
            GROUP BY token_player ");

        $tokenLimit=$this->getGameStateValue('numberOfToken');

        $result["nbToken"]=$maxToken-$tokenLimit;

        $result["tilesRemain"]=$this->getObjectListFromDB("SELECT COUNT(tile_id) ,tile_color FROM tile WHERE tile_location = 'Deck' GROUP BY tile_color");

        $result["places"]=$this->getPlaces($result["tiles"]);

        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $other_id => $other) {
            $token[$other_id] =
                $this->getGameStateValue('numberOfToken') -
                self::getUniqueValueFromDB("
                    SELECT count(token_id) as count
                    FROM token
                    WHERE token_player =".$other_id."
                    GROUP BY token_player");
        }
        $result['tokenPlayer'] = $token;

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "vizia";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        $this->setGameStateInitialValue( 'lastPlayer', (int)$this->getUniqueValueFromDB("SELECT MAX(player_no) FROM player") );
        $this->setGameStateInitialValue( 'lastRoundAnnounced', 0 );
        $this->setGameStateInitialValue( 'lowTile', 0 );


        // Init game statistics.
        //
        $this->initStat("table", "turns_number", 0);
        $this->initStat("player", "TilePurchased", 0);
        $this->initStat("player", "TokenCaptured", 0);
        $this->initStat("player", "WhellCompleted", 0);

        $this->createTiles();
        $this->createFirstWheel();
        $this->commonTile();
        $this->initiatePlayerHand();

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();
    }

    /**
     *
     * Debug function
     *
     * @throws BgaUserException
     */

    public function debug_getPurchasableTiles(){
        $test=$this->getPurchasableTiles();
        $this->notifyAllPlayers('getPurchasableTiles',"",$test);
    }


    public function debug_seePlace(){
        $test=$this->getPlaces(self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
                FROM tile
                WHERE tile_location = 'Board'"));

        $this->notifyAllPlayers('debug',"",$test);
    }


    public function debug_checkCanplay() {
        if($this->checkCanplay())
        $this->notifyAllPlayers('debug',$this->getActivePlayerId()."success",NULL);
        else
        $this->notifyAllPlayers('debug',$this->getActivePlayerId()."failed",NULL);
    }

    public function debug_tokenToRemove() {
        $maxToken=(int)$this->getUniqueValueFromDB("SELECT count(token_id) as nb
            FROM `token`
            WHERE token_player = ".$this->getActivePlayerId()."
            GROUP BY token_player ");

        $tokenLimit=$this->getGameStateValue('numberOfToken');

        self::notifyAllPlayers( "game_end_trigger", clienttranslate( 'Warning: The game will finish at the end of this round' ), array() );
        self::setGameStateValue("lastRoundAnnounced", 1);

        $tokenList = self::getObjectListFromDB("
            SELECT token_id id
            FROM token
            WHERE tmpToken= true");

        $this->notifyAllPlayers('tokenToRemove',
            "",
            [
                'nbToken' => ($maxToken-$tokenLimit),
                'token' => $tokenList,
            ]
        );
    }

    public function debug_tokenScore(int $x, int $y) {
        $dbres = self::DbQuery("SELECT token_id as id, token_player,
                board_token_x as x , board_token_y as y,
                triangleDown, triangleUpLeft, triangleDownLeft,
                triangleUp, triangleDownRight, triangleUpRight, tileGroup
                FROM token
                WHERE board_token_x = ".$x." and board_token_y = ".$y);

        $token = mysql_fetch_assoc( $dbres );
        $res=$this->checkToken($token);

        $this->CheckAdjacentToken($token);

        $message="resultat ".$res;
        $this->notifyAllPlayers('test',$message,array());

    }

    public function debug_takeNearlyAllToken (int $x, int $y) {
        static::DbQuery( "
            UPDATE token  SET token_player = ".$this->getActivePlayerId()."
            WHERE not( board_token_x = '".$x."' and board_token_y = '".$y."')");

        static::DbQuery( "
            UPDATE token  SET token_player = ".$this->getGameStateValue('lastPlayer')."
            WHERE  board_token_x = '".$x."' and board_token_y = '".$y."'");
/*
        static::DbQuery( "
            UPDATE token  SET token_player = NULL
            WHERE  board_token_x = '".$x."' and board_token_y = '".$y."'");
*/

    }

    public function debug_score() {
        $this->calculateScore(true);
    }


    public function debug_scoreTeam() {
        $this->setGameStateValue('teamPlay',1);
        $this->calculateScore(true);
        $this->setGameStateValue('teamPlay',0);

    }

    public function debug_commonTile() {
        $this->commonTile();
    }

    public function debug_token(int $x, int $y) {
        $this->CheckWhellsCompleted( $x,  $y);
        //send new token
        if( count($this->token) != 0)
        $this->notifyAllPlayers('newToken',
            clienttranslate($this->getActivePlayerName()." completed a wheel ".$x." ".$y),
            [
                'token' => $this->token,
            ]
        );

    }
    public function debug_forceChangeTile(){
        $this->setGameStateValue('communalTiles',6);

        $sql="UPDATE tile SET tile_location = 'Deck' where tile_location = 'common'";
        static::DbQuery($sql);

        $this->commonTile();

        $common = self::getObjectListFromDB("SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Common'");

        $tilesRemain=$this->getObjectListFromDB("
            SELECT COUNT(tile_id)
            FROM tile
            WHERE tile_location = 'Deck'
            GROUP BY tile_color");


        $this->notifyAllPlayers('nextPlayer',
            "",
            array(
                "commonTile" => $common,
                "tilesRemain" => $tilesRemain,
            )
        );
    }


    public function debug_checkTriangle(){

        $tokens=self::getCollectionFromDb("
            SELECT  token_id as id,token_player,
                    board_token_x as x , board_token_y as y,
                    triangleDown, triangleUpLeft, triangleDownLeft,
                    triangleUp, triangleDownRight, triangleUpRight
            FROM token");

        foreach( $tokens as $token ){
            $this->checkTriangle($token);
        }
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        if ($state["type"] === "activeplayer") {

            $tileNbr=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Common'");

            if($tileNbr != $this->getGameStateValue('communalTiles')){
                $sql="UPDATE tile
                    SET tile_location = 'Deck'
                    WHERE tile_location = 'Player' and
                    tile_location_arg = ".$this->getCurrentPlayerId();

                static::DbQuery($sql);
            }

            $this->gamestate->nextState("nextPlayer");

        }

        return;

    }
}
