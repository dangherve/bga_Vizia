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


// Suit and card data, added additional classes (suit_N) for custom CSS
const COLORS = [
    0 => ['name' => 'blue'],
    1 => ['name' => 'purple'],
    2 => ['name' => 'red'],
    3 => ['name' => 'orange'],
    4 => ['name' => 'yellow'],
    5 => ['name' => 'green'],
];

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
            "lastCardPlay" => 10,
            "numberOfToken" => 11,
            "last_round_announced" => 12,
            "last_player" => 13,

            //game option
            "multipleGame" =>100,
            "CommunalTiles" =>101,
            "groupBonus" =>102,
            "triangleBonus" =>103,
            "capture" =>104,
            "purchase" =>105,
        ]);

        $this->translatedColors = [
            0 => clienttranslate('Blue'),
            1 => clienttranslate('Purple'),
            2 => clienttranslate('Red'),
            3 => clienttranslate('Orange'),
            4 => clienttranslate('Yellow'),
            5 => clienttranslate('Green'),
        ];

    }


    public function CheckColor( $colorA,  $colorB){

        if( $colorB == null){
            $this->trace("tile empty");
            return true;
        }

        //+5 as if negative modulo with negative value do not work as we want
        if( ((($colorA+1)%6) == $colorB ) ||
            ((($colorA+5)%6) == $colorB )){
            return true;
        }

        return false;
    }

    //test should be done in UI also
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
$this->trace("************************");
$message=$x." ".$y." ".$TileCurentColor." ".$TileDownColor;
$this->dump("error down color",$message);
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

            $this->trace("*****************************************************");
            $msg= $x." ".$y;

            $this->dump("erreur",$msg);

            $debug= self::getCollectionFromDb("SELECT board_tile_x, board_tile_y, tile_color FROM tile where tile_location='Board'");

$msg = "\n\n\n";
foreach($debug as $debugTile){
    $msg.="[x:".$debugTile["board_tile_x"]."\ty:".$debugTile["board_tile_y"]."\tc:".$debugTile["tile_color"]."]\n";
}


            $this->dump("msg",$msg);
  $this->dump("debug",$debug);

            throw new \BgaUserException(self::_("Incorrect tile ".$x." ".$y." placement no adjacent tile"), true);
        }

    }


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
                "INSERT IGNORE INTO token (token_player, board_token_x, board_token_y)
                VALUES (%s, %s, %s)",
                $player_id,
                $x,
                $y,
            );

            static::DbQuery($sql);

            $sql="SELECT token_id
                FROM token WHERE board_token_x = ".$x." and board_token_y = ".$y;

//TOKEN LIMIT NOT IMPLEMENTED
            $tokenId = $this->getUniqueValueFromDB($sql);
            foreach ($tiles as $tile){

                $sql=sprintf("INSERT IGNORE INTO tokenTile (token_id, tile_id) VALUES (%s, %s)",
                        $tokenId,
                        $tile);

                static::DbQuery($sql);

            }

            $maxToken=(int)$this->getUniqueValueFromDB("SELECT max(nb) FROM (
                SELECT count(token_id) as nb
                FROM `token` GROUP BY token_player) AS tmp; ");

            $tokenLimit=$this->getGameStateValue('numberOfToken');

            if($maxToken>=$tokenLimit){
                self::notifyAllPlayers( "game_end_trigger", clienttranslate( 'Warning: The game will finish at the end of this round' ), array() );
                self::setGameStateValue("last_round_announced", 1);
            }



            $token[0]=[ 'player' => $player_id,
                     'x' => $x,
                     'y' => $y];

            $this->notifyAllPlayers(
                'newToken',clienttranslate($this->getActivePlayerName()." completed a wheel"),
                [
                    'token' => $token,
                ]
            );
        }

    }

//probably a better way to do it
    public function CheckWhellsCompleted( int $x , int $y){
$this->trace("===================================================");
$this->dump("x",$x);
$this->dump("y",$y);


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
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */

    public function actPlay(string $tilePlayed, string $tilePlayer, string $tileCommon): void
    {
        $message=$this->getActivePlayerName().' play ';

        //need to play at leas one tile
        if(strlen($tilePlayed)==0){
                throw new \BgaUserException(self::_("You need to play at least one tile"), true);
        }

        //verify that no player tile goes in common part
        //should not be usefull has UI should limit it be just in case
        if(strlen($tileCommon)!=0){
            $list_commonTile = explode(';',$tileCommon);
            foreach ($list_commonTile as $tile){
                if (strlen($tile)!=0){

                    $tileId = explode('_',$tile)[1];

                    $test = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE tile_location = 'Player' and tile_id = ".$tileId);
                    if($test ==null)
                        throw new \BgaUserException(self::_("Your tile can only go to the board"), true);

                }
            }
        }

        //update tile location and get missing info
        $list_playedTile = explode(';',$tilePlayed);
        foreach ($list_playedTile as $tile){

            if (strlen($tile)!=0){
                $tileData = explode(',',$tile);
                $tileId = (int)explode('_',$tileData[0])[1];
                $tileX = (int)$tileData[1];
                $tileY = (int)$tileData[2];

                $this->CheckTilePlacement((int)$tileId, $tileX, $tileY);

                $sql="UPDATE tile SET tile_location = 'Board',
                    board_tile_x = ".$tileX.", board_tile_y = ".$tileY."
                    WHERE tile_id = ".$tileId;

                static::DbQuery($sql);

                $tiles[$tileId]["x"] = $tileX;
                $tiles[$tileId]["y"] = $tileY;

                $tiles[$tileId]["id"] = $tileId;

                $tileColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE tile_id = ".$tileId);

                $tiles[$tileId]["color"]=$tileColor;

                $message.=COLORS[$tileColor]["name"]." tile ";

                $this->CheckWhellsCompleted( $tileX, $tileY);
            }
        }


$this->dump("tiles",$tiles);
        foreach ($tiles as $tile){

$this->dump("tile",$tile);

            $x = $tile["x"];
            $y = $tile["y"];


            if( ( $x + $y ) %2 == 0){
                $places_coords = array( ($x+1).'x'.$y, ($x-1).'x'.$y, $x.'x'.($y+1) );
            }else{
                $places_coords = array( ($x+1).'x'.$y, ($x-1).'x'.$y, $x.'x'.($y-1) );
            }

            $places[$x.'x'.$y] = 0;

            foreach( $places_coords as $coord ){
                if( ! isset( $places[ $coord ] ) )
                    $places[ $coord ] = 1;
            }
        }

        if(strlen($tilePlayer)!=0){
            $message.= "and take ";
            $list_playerTile = explode(';',$tilePlayer);
            foreach ($list_playerTile as $tile){
                if (strlen($tile)!=0){

                    $tileId = explode('_',$tile)[1];

                    if(array_key_exists($tileId,$tiles)){
                        throw new \BgaUserException(self::_("error tile both in player and played"), true);
                    }

                    $sql="UPDATE tile SET tile_location = 'Player',
                        tile_location_arg = ".$this->getActivePlayerId()."
                        WHERE tile_id = ".$tileId;
$this->trace("****************************************************************");
$this->dump("sql",$sql);
                    static::DbQuery($sql);

                $tileColor = $this->getUniqueValueFromDB("SELECT tile_color
                FROM tile WHERE tile_id = ".$tileId);

                $message.= COLORS[$tileColor]["name"]." ";
                }
            }
            $message.="to his reserve";
        }

        $placesFinal = array();
        foreach( $places as $coord => $value )
        {
            if( $value == 1 )
            {
                $xpos = strpos( $coord, 'x' );

                $x = substr( $coord, 0, $xpos );
                $y = substr( $coord, $xpos+1 );
                $placesFinal[] = array( 'x' => $x, 'y' => $y );
            }
        }


        //table
        $allTiles= self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
                FROM tile
                WHERE tile_location = 'Board'");

$this->trace("*******************************************************************");

$this->dump("allTiles",$allTiles);

        $this->notifyAllPlayers(
            'playedTile',clienttranslate($message),
            [
                'player_id' => $this->getActivePlayerId(),
                'tiles' => $tiles,
                'places' => $placesFinal,
            ]
        );

        // at the end of the action, move to the next state
        $this->gamestate->nextState("nextPlayer");
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
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

        $tilesRemain=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Deck'");

        $tilesNotPlayed=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Deck' or tile_location = 'common' or tile_location = 'Player'");


        $this->notifyAllPlayers(
            'nextPlayer',"",array(
                "commonTile" => $common,
                "tilesremain" => $tilesRemain,
            )
        );

$this->dump("**********tilesNotPlayed",$tilesNotPlayed);

        if(($tilesNotPlayed == 0) || ((self::getGameStateValue('last_round_announced') == 1) && ($player_data[$player_id]['player_no'] == self::getGameStateValue('last_player')))){
$this->trace("**********score");

            $this->gamestate->nextState("calculateScore");

        }else{
$this->trace("**********continue");

            if(sizeof($common) == 0){
                $this->trace("need check player tile");

                do{
                    $player_id = (int)$this->getActivePlayerId();

                    $privateTileRemain = self::getUniqueValueFromDB("SELECT COUNT(tile_id)
                    FROM tile
                    WHERE tile_location = 'Player' and tile_location_arg = ".$player_id);

                    if($privateTileRemain == 0){
                        $this->notifyAllPlayers(
                            'passPlayer',clienttranslate($this->getActivePlayerName()." did not have any tile left his turn is skipped"),
                            [
                            ]
                        );
                        $this->activeNextPlayer();

                    }
                }while ($privateTileRemain == 0);
            }
            $this->gamestate->nextState("playerTurn");
        }
    }

    public function checkToken($token): int {
        $score=0;


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
                $score+=2;
                self::DbQuery(sprintf("UPDATE token SET triangleDown = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleDownLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleDownRight = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if($token["triangleUp"] == 0){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]+1));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]+1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                $score+=2;
                self::DbQuery(sprintf("UPDATE token SET triangleUp = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleUpLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleUpRight = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if($token["triangleUpLeft"] == 0){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]+2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]-1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                $score+=2;
                self::DbQuery(sprintf("UPDATE token SET triangleUpLeft = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleUpRight = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleUp = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if($token["triangleDownLeft"] == 0){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]+2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]+1)." and board_token_y = ".($token["y"]+1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                $score+=2;
                self::DbQuery(sprintf("UPDATE token SET triangleDownLeft = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleDownRight = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleDown = 1  WHERE token_id = '%s'", $token3));
            }
        }

      if($token["triangleUpRight"] == 0){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]-2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]-1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                $score+=2;
                self::DbQuery(sprintf("UPDATE token SET triangleUpRight = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleUpLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleUp = 1  WHERE token_id = '%s'", $token3));
            }
        }

        if($token["triangleDownRight"] == 0){
            $token2 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]-2)." and board_token_y = ".($token["y"]));

            $token3 = self::getUniqueValueFromDB("SELECT token_id
                    FROM token
                    WHERE token_player =".$token["token_player"]." and
                    board_token_x = ".($token["x"]-1)." and board_token_y = ".($token["y"]+1));

            if ( ( $token2 != null ) and ( $token3 != null ) ){
                $score+=2;
                self::DbQuery(sprintf("UPDATE token SET triangleDownRight = 1  WHERE token_id = '%s'", $token["id"]));
                self::DbQuery(sprintf("UPDATE token SET triangleDownLeft = 1  WHERE token_id = '%s'", $token2));
                self::DbQuery(sprintf("UPDATE token SET triangleDown = 1  WHERE token_id = '%s'", $token3));
            }
        }

        return $score;
    }

    public function calculateScore(): void {

        $players = $this->loadPlayersBasicInfos();


        $points = [['str' => "points", 'args' => []]];
        $pointsTriange = [['str' => "points triangle", 'args' => []]];

        $nameRow = [''];
        foreach ($players as $player_id => $player) {
            $nameRow[$player_id] = [
                'str' => '${player_name}',
                'args' => ['player_name' => $this->getPlayerNameById($player_id)],
                'type' => 'header',
            ];

            $points[$player_id]=0;
            $pointsTriange[$player_id]=0;
        }

        $tokens = self::getCollectionFromDb("SELECT token_id as id,token_player,
                board_token_x as x , board_token_y as y,
                triangleDown, triangleUpLeft, triangleDownLeft,
                triangleUp, triangleDownRight, triangleUpRight
                FROM token");

        foreach( $tokens as $token ){
            $colors = self::getCollectionFromDb("SELECT tile_color
                FROM tile, tokenTile
                WHERE tile.tile_id = tokenTile.tile_Id and
                    tokenTile.token_id = ".$token["id"]."
                    GROUP BY tile_color");
$this->trace("**********************************************");
$this->dump("colors",$colors);
$this->dump("nb",sizeof($colors) );
            // multicolor
            if (sizeof($colors) == 6){
                $points[$token["token_player"]]+=3;
            // bicolor
            }else if (sizeof($colors) == 2){
                 $points[$token["token_player"]]+=2;
            //tricolor
            }else if (sizeof($colors) == 3){
                 $points[$token["token_player"]]+=1;
            //error
            }else{
                 $this->trace( "Error calculation point error" );
            }

            if($this->getGameStateValue('triangleBonus')){
$this->dump("*************************token",$token);
                $pointsTriange[$token["token_player"]]+=$this->checkToken($token);
            }

        }


            if($this->getGameStateValue('groupBonus')){

            }

        $table = [$nameRow,$points];
            if($this->getGameStateValue('triangleBonus')){
                $table[] = $pointsTriange;
            }

        $this->notifyAllPlayers("tableWindow", clienttranslate(""), [
            "id" => 'finalScoring',
            "title" => "",
            "table" => $table,
            "closing" => clienttranslate("Close"),
        ]);

        foreach ($players as $player_id => $player) {
            $pointSum=$points[$player_id];
            self::DbQuery(sprintf("UPDATE player SET player_score = player_score + %d WHERE player_id = '%s'", $pointSum, $player_id));
        }


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

        $result['final_round'] = self::getGameStateValue('last_round_announced');

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        $result["hand"] = self::getObjectListFromDB("SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Player' and tile_location_arg = ".$player_id);

$sql="SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Player' and tile_location_arg = ".$player_id;
$this->dump("sql",$sql);

$this->dump("hand",$result["hand"]);

        $result["common"] = self::getObjectListFromDB("SELECT tile_id id, tile_color color
                FROM tile
                WHERE tile_location = 'Common'");

        //table
        $result["tiles"] = self::getCollectionFromDb("SELECT tile_id id,board_tile_x x,board_tile_y y, tile_color color
                FROM tile
                WHERE tile_location = 'Board'");

        $result["token"] = self::getObjectListFromDB("SELECT token_id id,board_token_x x,board_token_y y, token_player player
                FROM token");

        $result["tilesremain"]=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Deck'");

        $places=[];
        foreach ($result["tiles"] as $tile) {

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

        $result['places'] = array();
        foreach( $places as $coord => $value )
        {
            if( $value == 1 )
            {
                $xpos = strpos( $coord, 'x' );

                $x = substr( $coord, 0, $xpos );
                $y = substr( $coord, $xpos+1 );
                $result['places'][] = array( 'x' => $x, 'y' => $y );
            }
        }

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

        $this->setGameStateInitialValue( 'last_player', (int)$this->getUniqueValueFromDB("SELECT MAX(player_no) FROM player") );

        $this->setGameStateInitialValue( 'last_round_announced', 0 );

        $this->createTiles();
        $this->createFirstWheel();
        $this->commonTile();
        $this->initiatePlayerhand();

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();
    }


    public function createTiles() {
        $sql = "INSERT INTO tile ( tile_color, tile_location) VALUES ";
        $values = [];

        $nbTile=$this->getGameStateValue('multipleGame')*12;

        if ($nbTile == 0){
            $nbTile=6;
        }

        $this->setGameStateValue('numberOfToken',$nbTile);

        foreach(COLORS as $color_id => $color){
            for($value=1; $value<=$nbTile; $value++) {
                $values[] = "('".$color_id."', 'Deck')";
            }
        }

        $sql .= implode( ',', $values );
        static::DbQuery( $sql );

    }

    public function debug_tokenScore(int $x, int $y) {
        $dbres = self::DbQuery("SELECT token_id as id, token_player,
                board_token_x as x , board_token_y as y,
                triangleDown, triangleUpLeft, triangleDownLeft,
                triangleUp, triangleDownRight, triangleUpRight
                FROM token
                WHERE board_token_x = ".$x." and board_token_y = ".$y);

        $token = mysql_fetch_assoc( $dbres );
        $res=$this->checkToken($token);

        $message="resultat ".$res;
        $this->notifyAllPlayers(
            'test',$message,array(
            )
        );


    }


    public function debug_testTilePlacement() {
        $this->calculateScore();
    }


    public function debug_score() {
        $this->calculateScore();
    }

    public function debug_commonTile() {
        $this->commonTile();
    }

    public function debug_token(int $x, int $y) {
        $this->CheckWhellsCompleted( $x,  $y);
    }

    public function initiatePlayerhand() {
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            $tiles = $this->pickTile(2);
            static::DbQuery( "
            UPDATE tile set tile_location = 'Player', tile_location_arg = ".$player_id."
            WHERE tile_id = ".$tiles[0]." or tile_id = ".$tiles[1]);
        }
    }

    public function debug_createFirstWheel() {
        $this->createFirstWheel();
    }

    public function debug_emptyBoard() {
            // Remove it from the deck
            static::DbQuery( "DELETE FROM tile where tile_location = 'board'" );
    }


    public function debug_addColor(int $color_id) {

        //create initial wheel
//        foreach($toto as $color_id => $color){
            $dbres = static::DbQuery( "
            SELECT tile_id
            FROM tile WHERE
            tile_color='".$color_id."'ORDER BY RAND() LIMIT 0,1", false );

            $row = mysql_fetch_assoc( $dbres );
            if( ! $row )
                throw new feException( "Unable to find start tile in deck" );

            $x=FIRST_WHEEL[$color_id]['x'];
            $y=FIRST_WHEEL[$color_id]['y'];

            static::DbQuery( "
            UPDATE tile SET board_tile_x = ".$x.", board_tile_y = ".$y.", tile_location = 'board'
            WHERE  tile_id = '".$row['tile_id']."' " );

        }


    protected function commonTile(){
        $tileNbr=(int)$this->getUniqueValueFromDB("SELECT COUNT(tile_id) FROM tile WHERE tile_location = 'Common'");

        $tileToPick=$this->getGameStateValue('CommunalTiles')-$tileNbr;

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

    }


    public function createFirstWheel() {

        //create initial wheel
        foreach(COLORS as $color_id => $color){
            $tile_id = $this->getUniqueValueFromDB("SELECT tile_id
            FROM tile WHERE
            tile_color='".$color_id."'ORDER BY RAND() LIMIT 0,1");

            $x=FIRST_WHEEL[$color_id]['x'];
            $y=FIRST_WHEEL[$color_id]['y'];

            static::DbQuery( "
            UPDATE tile SET board_tile_x = ".$x.", board_tile_y = ".$y.", tile_location = 'board'
            WHERE  tile_id = '".$tile_id."'");

        }
    }



    protected function pickTile( $number ){

        $tilesCollection = self::getCollectionFromDb( "SELECT tile_id id  FROM tile WHERE tile_location = 'Deck' ORDER BY RAND() LIMIT 0,".$number, false );

        $tiles = [];

        foreach ( $tilesCollection as $deck_tile_id => $tileId ) {
            $tiles[] = $tileId['id'];
        }

        return $tiles;
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
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }
}
