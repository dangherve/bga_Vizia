/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * vizia implementation : © <Herve Dang> <dang.herve@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * vizia.js
 *
 * vizia user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

var isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;
var debug = isDebug ? console.info.bind(window.console) : function() {};
var error = isDebug ? console.error.bind(window.console) : function() {};

var debugStatus = "debugOFF"
var colorBlindStatus = "colorBlindOFF"
var memoryHelpStatus = "memoryHelpOFF"
//        <text class="debug ${debugStatus}" x="${tpl.coord[0]+60}" y="${tpl.coord[1]-70}" font-size="80" fill="${tpl.coord[2]}">${tpl.colorText}</text>

const jstpl_triangle = (tpl) => `
<div id="tile_${tpl.tile_id}" class="tile">
    <svg width='${tpl.tile_size}px' viewBox="0 0 400 350" >
        <polygon class="${tpl.class} ${tpl.possibleColor} ${tpl.disabledStatus}"
          points="${tpl.points[0][0]},${tpl.points[0][1]} ${tpl.points[1][0]},${tpl.points[1][1]} ${tpl.points[2][0]},${tpl.points[2][1]}"
          stroke="`+tpl.color+`" stroke-width=10 stroke-opacity="`+tpl.opacity+`"
          fill="`+tpl.color+`" fill-opacity="`+tpl.opacity+`" />
        <text class="debug ${debugStatus}" x="${tpl.coord[0]}" y="${tpl.coord[1]-25}" font-size="60" fill="${tpl.coord[2]}">${tpl.tile_id}</text>
        <text class="debug ${debugStatus}" x="${tpl.coord[0]}" y="${tpl.coord[1]+25}" font-size="60" fill="${tpl.coord[2]}">${tpl.x}x${tpl.y}</text>
        <text class="colorBlind ${tpl.disabledStatus} ${colorBlindStatus}" x="${tpl.coord[0]+25}" y="${tpl.coord[1]}" font-size="150" fill="${tpl.coord[2]}">${tpl.colorText}</text>
    </svg>
</div>`;

const jstpl_circle = (tpl) => `
<svg width='${tpl.token_size}' height='${tpl.token_size}' >
<!--
<line x1="${tpl.token_size/2}" y1="0" x2="${tpl.token_size/2}" y2="${tpl.token_size}" style="stroke:white;stroke-width:1" />
<line y1="${tpl.token_size/2}" x1="0" y2="${tpl.token_size/2}" x2="${tpl.token_size}" style="stroke:white;stroke-width:1" />
-->
<circle class="${tpl.player} ${tpl.class}" r="${tpl.token_size/2-5}" cx="${tpl.token_size/2}" cy="${tpl.token_size/2}" opacity="1" fill="${tpl.color}" />

<text class="debug ${debugStatus} " style="z-index: 10" x="25" y="25" font-size="18" fill="red" >${tpl.id}</text>
<text class="debug ${debugStatus} " style="z-index: 10" x="20" y="45" font-size="18" fill="blue" >${tpl.x}x${tpl.y}</text>
<text class="debug ${debugStatus} " style="z-index: 10" x="25" y="65" font-size="18" fill="green" >${tpl.group}</text>

</svg>`
const jstpl_circle2 = (tpl) => `
<svg width='${tpl.token_size}' height='${tpl.token_size}' >
<circle class="${tpl.player}" r="${tpl.token_size/2-5}" cx="${tpl.token_size/2}" cy="${tpl.token_size/2}" opacity="1" fill="${tpl.color}" />
</svg>`

const jstpl_token = (tpl) => `
<div class='token' id="token_${tpl.id}" style="width:${tpl.token_size}px;top:${tpl.top}px;left:${tpl.left}px;">`
+jstpl_circle(tpl)+`</div>`;

const jstpl_token_player_board = (id, tpl) => `<span id="${id}_wrap" class="partwrap"><div id="${id}" class="tokenPlayer">`+jstpl_circle2(tpl)+`</div></span>`;

//
//
const jstpl_element_on_map = (tpl) => `
<div id="place_${tpl.x}x${tpl.y}" class="map" style="top:${tpl.top}px;left:${tpl.left}px;">`
    +jstpl_triangle(tpl)+
`</div>`

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "./modules/scrollmapWithZoom",
    "./modules/konami"
//    "ebg/scrollmap"
],
function (dojo, declare) {
    return declare("bgagame.vizia", ebg.core.gamegui, {
        constructor: function(){
            debug('vizia constructor');

            this.colorSection = 1;

            this.tileColor = {
                0: {
                    6: '#000000',
                    0: '#1976D2',//blue
                    1: '#9C27B0',//purple
                    2: '#D32F2F',//red
                    3: '#F57C00',//orange
                    4: '#FDD835', //yellow
                    5: '#388E3C',//green
                },
                1: {
                    6: 'black',
                    0: 'blue',
                    1: 'purple',
                    2: 'red',
                    3: 'orange',
                    4: 'yellow',
                    5: 'green',
                },
            };

            this.color = {
                    0: 'blue',
                    1: 'purple',
                    2: 'red',
                    3: 'orange',
                    4: 'yellow',
                    5: 'green',
            }

            this.tileRotate = {
                0: { 0 : [0,350] ,
                     1 : [200.0, 0] ,
                     2 : [400.0,350] },
                1: { 0 : [0,0] ,
                     1 : [400.0, 0] ,
                     2 : [200.0,350] },
            };

            this.tileCoord = {
                0: [130,255,"black"],
                1: [130,175,"white"],
            };

            this.tilePriority = {
                0: "priority1",
                1: "priority2"
            }
            this.tile_sizeW = 400/4;
            this.tile_sizeH = 350/4;

            this.token_size = 40;

            this.tile_sizeWPrivate = 400/5;

            this.current_tile = "";
            this.current_token = "";

            this.auto_scroll = true;
            this.players = null;

            this.playedTile = {}
            this.playerTile = {}
            this.token =  {}

            this.handler = {}

            this.finalRound = 0

            this.tmpId=1000;
            this.nbToken = 0
            this.purchase=0;

        },

        /*
            setup:

            This method must set up the game user interface according to current game situation specified
            in parameters.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)

            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */


        initiateTemplate: function(){
            // Example to add a div on the game area
            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="commonTile" class="whiteblock"></div>
                <div id="tilePlayed" class="whiteblock ${debugStatus} "></div>
                <div id="tilePlayer" class="whiteblock ${debugStatus} "></div>
                <div id="remain" class="whiteblock ${debugStatus} "></div>
                <div id="tileId" class="whiteblock ${debugStatus} "></div>

                <div id="memoryHelp" class="whiteblock">
                    <svg height="200px" width="200px" >
                        <polygon fill="${this.tileColor[this.colorSection][0]}" points="100,100 180.0,100.0 140.0,169.282" stroke="black" stroke-width="1" />
                        <text class="colorBlind ${colorBlindStatus} " x="130" y="140" font-size="50" fill="black">
                            0
                        </text>
                        <polygon fill="${this.tileColor[this.colorSection][1]}" points="100,100 140.0,169.282 60.0,169.282" stroke="black" stroke-width="1" />
                        <text class="colorBlind ${colorBlindStatus} " x="90" y="160" font-size="50" fill="black">
                            1
                        </text>
                        <polygon fill="${this.tileColor[this.colorSection][2]}" points="100,100 60.0,169.282 20.0,100.0" stroke="black" stroke-width="1" />
                        <text class="colorBlind ${colorBlindStatus} " x="45" y="140" font-size="50" fill="black">
                            2
                        </text>
                        <polygon fill="${this.tileColor[this.colorSection][3]}" points="100,100 20.0,100.0 60.0,30.718" stroke="black" stroke-width="1" />
                        <text class="colorBlind ${colorBlindStatus} " x="45" y="90" font-size="50" fill="black">
                            3
                        </text>
                        <polygon fill="${this.tileColor[this.colorSection][4]}" points="100,100 60.0,30.718 140.0,30.718" stroke="black" stroke-width="1" />
                        <text class="colorBlind ${colorBlindStatus} " x="85" y="70" font-size="50" fill="black">
                            4
                        </text>
                        <polygon fill="${this.tileColor[this.colorSection][5]}" points="100,100 140.0,30.718 180.0,100.0" stroke="black" stroke-width="1" />
                        <text class="colorBlind ${colorBlindStatus} " x="130" y="90" font-size="50" fill="black">
                            5
                        </text>
                    </svg>
                </div>

                <div id="map_container">
                    <div id="map_scrollable">
                    </div>
                    <div id="map_surface">
                    </div>
                    <div id="map_scrollable_oversurface">
                        <div id="places_container"></div>
                    </div>
                    <a id="movetop" href="#"></a>
                    <a id="moveleft" href="#"></a>
                    <a id="moveright" href="#"></a>
                    <a id="movedown" href="#"></a>
                </div>
            `);

            this.easterEgg = new KonamiCode(() => {
                isDebug=true
                this.debugMenu()
            });

            this.scrollmap = new ebg.scrollmapWithZoom();
//            this.scrollmap = new ebg.scrollmap(); // declare an object (this can also go in constructor)

            this.scrollmap.zoom = 1;
            this.scrollmap.btnsDivOnMap = false;
            this.scrollmap.btnsDivPositionOutsideMap = ebg.scrollmapWithZoom.btnsDivPositionE.Left

            this.scrollmap.bIncrHeightBtnVisible=false;
            this.scrollmap.bInfoBtnVisible=true;

            // Make map scrollable
            this.scrollmap.create( $('map_container'),$('map_scrollable'),$('map_surface'),$('map_scrollable_oversurface') ); // use ids from template
            this.scrollmap.setupOnScreenArrows( 250 ); // this will hook buttons to onclick functions with 150px scroll step

        },


        commonTile: function( common){

            document.getElementById('commonTile').innerHTML= "";

            for( i in common )
            {
                var tile = common[i];

                tpl = {};
                tpl.tile_id=tile.id;
                tpl.top=0;
                tpl.left=0;
                tpl.x="C";
                tpl.y=i;
                tpl.class="commonTile";
                tpl.points=this.tileRotate[0];
                tpl.coord=this.tileCoord[0];
                tpl.mapClass=""
                tpl.tile_size = this.tile_sizeW;
                tpl.color=this.tileColor[this.colorSection][tile.color];
                tpl.colorText=tile.color;
                tpl.possibleColor=""
                tpl.disabledStatus=""
                tpl.opacity=1;

                elementToAdd = jstpl_triangle( tpl )

                document.getElementById('commonTile').insertAdjacentHTML('beforeend',
                    elementToAdd );
            }
        },

        addElement: function (elements){
            playable=false;
            for( i in elements ){
                var element = elements[i];

                tpl = {};
                tpl.top=element.y*(this.tile_sizeW-12);
                tpl.left=element.x*(this.tile_sizeH+17)/2;
                tpl.x=element.x;
                tpl.y=element.y;

                var orientation = (Math.abs(Number(element.x)+Number(element.y))%2)

                tpl.points=this.tileRotate[orientation]
                tpl.coord=this.tileCoord[orientation]
                tpl.tile_size = this.tile_sizeW;
                tpl.mapClass=this.tilePriority[orientation]


                if(element.id != null){
                    tpl.tile_id=element.id;
                    tpl.class="boardTile";
                    tpl.color=this.tileColor[this.colorSection][element.color];
                    tpl.colorText=element.color
                    tpl.opacity=1;
                    tpl.possibleColor=""
                    tpl.disabledStatus=""
                }else{
                    tpl.tile_id=this.tmpId;
                    this.tmpId++;
                    tpl.class="boardPlace";
                    tpl.color=this.tileColor[this.colorSection][6];
                    tpl.colorText=6
                    tpl.opacity=0.2;
                    playable=true
                    tpl.disabledStatus="disabled"

                    if (element.possibleColor[1] != null){
                        tpl.possibleColor=element.possibleColor[0]+" "+element.possibleColor[1];
                    }else if (element.possibleColor[0] != null){
                        tpl.possibleColor=element.possibleColor[0];
                    }else{
                        tpl.possibleColor=""
                    }
                }

                //get current place
                place="place_"+tpl.x+"x"+tpl.y
                htmlelement=document.getElementById(place)

                //if not exist just add it
                if (htmlelement == null){

                    elementToAdd = jstpl_element_on_map( tpl )
                    dojo.place(elementToAdd, 'places_container' );

                }else{
                    //check if we have to replace a place by a tile
                    if (tpl.class == "boardTile"){

try{
                        htmlelement.parentNode.removeChild(htmlelement)

                        elementToAdd = jstpl_element_on_map( tpl )
                        dojo.place(elementToAdd, 'places_container' );
}catch(err){
if(isDebug)
alert("*** check dom ****")
}
                    //update Place "restriction"
                    }else if ((tpl.class == "boardPlace") && (htmlelement.getElementsByClassName("boardTile").length ==0 ) ){

                        //instead of update className as we got some bug with filter/include
                        //we replace it
try{
                        htmlelement.parentNode.removeChild(htmlelement)

                        elementToAdd = jstpl_element_on_map( tpl )
                        dojo.place(elementToAdd, 'places_container' );
}catch(err){
if(isDebug)
alert("*** check dom ****")
}

/*
                        possibleColorOLD=htmlelement.getElementsByClassName("boardPlace")[0].className.baseVal.split(' ')
                        result = possibleColorOLD.filter(value => element.possibleColor.includes(value));
                        if (result[1] != null){
                            htmlelement.getElementsByClassName("boardPlace")[0].className.baseVal="boardPlace "+result[0]+" "+result[1]+" disabled";
                        }else if (result[0] != null){
                            htmlelement.getElementsByClassName("boardPlace")[0].className.baseVal="boardPlace "+result[0]+" disabled";
                        }else{
                            htmlelement.getElementsByClassName("boardPlace")[0].className.baseVal="boardPlace"+" disabled"
                        }
*/
                    }


                    //do nothing if otherwise
                }

            }

            this.refreshHandler();

        },

        removePlayerToken: function (tokens){
            for( i in tokens ){
                var token = tokens[i];
                tokenPlayer=$('tokenPlayer_'+token.player)
                try{
                    tokenPlayer.removeChild(tokenPlayer.lastChild)
                }catch(err){
                    //do nothin player put too much tooken he has to remove the excess
                }

            }
        },

        addToken: function (tokens){

            for( i in tokens ){
                var token = tokens[i];
                if(token.player != null){

                    tpl = {};

                    if(debugStatus == "debugOFF"){
                        tpl.top=67;
                        tpl.left=82;
                        tpl.token_size= this.token_size;
                    }else{
                        tpl.top=47;
                        tpl.left=61;
                        tpl.token_size= 80;
                    }

                    tpl.x=token.x
                    tpl.y=token.y

                    if ((this.purchase == 1 ) && (token.player == this.playerId))
                        tpl.class="clickableToken"
                    else
                        tpl.class=""

                    tpl.color="#"+this.players[token.player].color ;
                    tpl.id=token.id
                    tpl.player=token.player

                    if(token.tileGroup ==null){
                        tpl.group=""
                    }else{
                        tpl.group=token.group
                    }

                    document.getElementById("place_"+token.x+"x"+token.y).innerHTML += jstpl_token(tpl);

                }
            }

        },


        displayFinalRoundWarning: function() {
            if (this.finalRound == 1) {
                dojo.place('<div id="finalRound"></div>', 'generalactions')
                $('finalRound').innerHTML = _('Warning: This is the final round');
            }
        },

        displayLowTileWarning: function() {
            if (this.lowTile == 1) {
                dojo.place('<div id="lowTile"></div>', 'generalactions')
                $('lowTile').innerHTML = _('Warning: Less than 10 tile');
            }
        },


        initiatePlayer: function (hand){
            if( ! this.isSpectator ){
                var hand = hand;
                dojo.place(`
                <div id="hand0" ></div>
                <div id="hand1" ></div>
                <br class="clear" />`, 'current_player_board' );

                dojo.style( 'hand0', 'backgroundPosition', '50% 50%' );

                tpl = {};
                tpl.top=0;
                tpl.left=0;
                tpl.x="h";
                tpl.tile_size = this.tile_sizeW;

                tpl.y=1;
                tpl.points=this.tileRotate[0];
                tpl.coord=this.tileCoord[0];
                tpl.disabledStatus="";
                tpl.possibleColor="";

                try{
                    tpl.tile_id=hand[0].id;
                    tpl.color=this.tileColor[this.colorSection][hand[0].color];
                    tpl.colorText=hand[0].color;
                    tpl.opacity=1;
                    tpl.class="handTile";

                    document.getElementById('hand0').insertAdjacentHTML('beforeend',
                        jstpl_triangle( tpl ));

                }catch(err){
                    tpl.tile_id=this.tmpId
                    this.tmpId++
                    tpl.color=this.tileColor[this.colorSection][6];
                    tpl.colorText=6;
                    tpl.opacity=0.2;
                    tpl.class="handPlace";

                    document.getElementById('hand0').insertAdjacentHTML('beforeend',
                        jstpl_triangle( tpl ));

                }

                dojo.style( 'hand1', 'backgroundPosition', '50% 50%' );

                tpl.y=2;
                tpl.points=this.tileRotate[1];
                tpl.coord=this.tileCoord[1];
                tpl.mapClass=""

                try{
                    tpl.tile_id=this.tmpId
                    this.tmpId++
                    tpl.tile_id=hand[1].id;
                    tpl.colorText=hand[1].color;
                    tpl.color=this.tileColor[this.colorSection][hand[1].color];
                    tpl.opacity=1;
                    tpl.class="handTile";

                    document.getElementById('hand1').insertAdjacentHTML('beforeend',
                        jstpl_triangle( tpl ));

                }catch(err){
                    tpl.tile_id=this.tmpId
                    this.tmpId++
                    tpl.color=this.tileColor[this.colorSection][6];
                    tpl.colorText=6;
                    tpl.opacity=0.2;
                    tpl.class="handPlace";

                    document.getElementById('hand1').insertAdjacentHTML('beforeend',
                        jstpl_triangle( tpl ));
                }
            }

        },

        onMemoryHelpChanged: function (pref_value) {

            dojo.query('#memoryHelp').removeClass(memoryHelpStatus);

            if( pref_value == 0 ){
                memoryHelpStatus = "memoryHelpOFF"
            }else{
                memoryHelpStatus = "memoryHelpON"
            }

            dojo.query('#memoryHelp').addClass(memoryHelpStatus);
        },

        onColorBlindChanged: function (pref_value) {

            elementToChange = dojo.query('.colorBlind');
            if( pref_value == 0 ){
                colorBlindStatusOld = "colorBlindON"
                colorBlindStatus = "colorBlindOFF"

            }else{
                colorBlindStatusOld = "colorBlindOFF"
                colorBlindStatus = "colorBlindON"
            }
            for (i = 0 ; i < elementToChange.length; i++ ){
                elementToChange[i].className.baseVal=elementToChange[i].className.baseVal.replace(colorBlindStatusOld,colorBlindStatus)
            }
        },

        onColorChanged: function (pref_value) {
            this.colorSection = pref_value;
        },

        updatePurchasableTiles: function(tiles){
        //TO CORRECT
            elementToChange = dojo.query( ".purchasableTile" )

            for (i = 0 ; i < elementToChange.length; i++ ){
                elementToChange[i].className.baseVal="boardTile"
            }

            for( i in tiles ){
                element=$("tile_"+tiles[i])

                svg = element.getElementsByTagName("svg")[0]
                polygon = svg.getElementsByTagName("polygon")[0]
                polygon.className.baseVal+=" purchasableTile"

            }
            this.refreshHandler();
        },

        refreshHandler: function (){

            this.disconnectAll();

            this.connectClass('commonTile', 'onclick', 'onAction');
            this.connectClass('commonPlace', 'onclick', 'onAction');

            this.connectClass('handTile', 'onclick', 'onAction');
            this.connectClass('handPlace', 'onclick', 'onAction');

//can not be click default rule
            if( this.purchase ==1 ){
                this.connectClass('purchasableTile', 'onclick', 'onAction');
                this.connectClass('purchasedTile', 'onclick', 'onAction');
                this.connectClass(this.playerId , 'onclick', 'onAction');
            }

            this.connectClass('boardPlace', 'onclick', 'onAction');
            this.connectClass('playedTile', 'onclick', 'onAction');
        },

        onGameUserPreferenceChanged: function(pref_id, pref_value) {
            switch (pref_id) {
                case 100:
                    this.onMemoryHelpChanged(pref_value)
                    break;
                case 101:
                    this.onColorBlindChanged(pref_value)
                    break;
                case 102:
                    this.onColorChanged(pref_value)
                    break;
            }
        },

        playerToken: function(token) {

            tpl = {};

            tpl.token_size= this.token_size;

            tpl.x='j'

            tpl.class=""
            tpl.group=""
            for( player_id in this.players )
            {
                var player_board_div = $('player_board_'+player_id);
                dojo.place('<div id="tokenPlayer_'+player_id+'" class="token_stock"></div><br class="clear" />', player_board_div );

                var player_token_div = $('tokenPlayer_'+player_id);

                for( i = 0; i< token[player_id]; i++ )
                {
                    tpl.y=i
                    tpl.color="#"+this.players[player_id].color ;
                    tpl.id=i
                    tpl.player=player_id

                    dojo.place(jstpl_token_player_board(i,tpl), player_token_div );
                }
            }

        },

        tokenToDecide: function(tokens) {
            var newToken = []

            for( i in tokens ){

                    circle=document.getElementById("token_"+tokens[i].id).getElementsByTagName("svg")[0].getElementsByTagName("circle")[0]

                    circle.className.baseVal=circle.className.baseVal+" selectableToken"
            }

        },


        setup: function( gamedatas )
        {
            debug( "Starting game setup" );

            this.onColorChanged(this.getGameUserPreference(102))

            this.purchase = gamedatas.purchase

            this.playerId = Number(gamedatas.player_id);

            this.players = gamedatas.players;

            this.initiateTemplate();

            this.commonTile(gamedatas.common);
            this.initiatePlayer(gamedatas.hand);
            this.playerToken(gamedatas.tokenPlayer);

            this.addElement(gamedatas.places);
            this.addElement(gamedatas.tiles);

            this.updatePurchasableTiles(gamedatas.purchasableTiles)

            this.addToken(gamedatas.token);

            this.finalRound=gamedatas.finalRound
            this.displayFinalRoundWarning();

            this.lowTile=gamedatas.lowTile
            this.displayLowTileWarning();

            this.tileRemain(gamedatas.tilesRemain);

            this.nbToken=gamedatas.nbToken

            this.tokenToDecide(gamedatas.tokenToDecide)

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            this.refreshHandler();
            debug( "Ending game setup" );
        },

        tileRemain: function( tilesRemain )
        {
            total=0
            msg=""
            for( i in tilesRemain ){
                total+= parseInt (tilesRemain[i]["COUNT(tile_id)"])
                msg+=this.color[i]+": "+tilesRemain[i]["COUNT(tile_id)"]+"<br/>"
            }
            msg+="Total: "+total+"<br/>"

            document.getElementById('remain').innerHTML=msg

        },


        ///////////////////////////////////////////////////
        //// Interface action


        /* This enable to inject translatable styled things to logs or action bar */
        /* @Override */
        format_string_recursive : function(log, args) {
            try {

                if (log && args && !args.processed) {
                    args.processed = true;


                    if (args.tilesPlayedUI !== undefined) {
                        text=""
                        for(var i in args.tilesPlayedUI) {
                            text+='<strong style="color:'+this.tileColor[this.colorSection][args.tilesPlayedUI[i].id]+'">'+args.tilesPlayedUI[i].text+" tile </strong>"
                        }
                        args.tilesPlayedUI= dojo.string.substitute("${tilesPlayedUI}", {'tilesPlayedUI' : text});
                    }

                    if (args.tilesTakenUI !== undefined) {
                        text=""
                        for(var i in args.tilesTakenUI) {
                            text+='<strong style="color:'+this.tileColor[this.colorSection][args.tilesTakenUI[i].id]+'">'+args.tilesTakenUI[i].text+" tile </strong>"
                        }
                        args.tilesTakenUI= dojo.string.substitute("${tilesTakenUI}", {'tilesTakenUI' : text});
                    }

                }
            } catch (e) {
                console.error(log,args,"Exception thrown", e.stack);
            }
            return this.inherited(arguments);
        },
        ///////////////////////////////////////////////////
        //// Game & client states


        checkPlace: function (){
            x=prompt("x")
            y=prompt("y")
            this.bgaPerformAction('actDebugCheckTile', {
                x: x,
                y: y
            });

        },

        debugOn: function (){
            debugStatus = "debugON"
            toto = document.getElementsByClassName('debug')
            for (i = 0 ; i < toto.length; i++ ){
                toto[i].className.baseVal='debug debugON'
            }
            document.getElementById('remain').className ='whiteblock debugON'
            document.getElementById('tileId').className ='whiteblock debugON'
            document.getElementById('tilePlayed').className ='whiteblock debugON'
            document.getElementById('tilePlayer').className ='whiteblock debugON'

            toto = document.getElementsByClassName('token')
            for (i = 0 ; i < toto.length; i++ ){
                toto[i].style.top="47px"
                toto[i].style.left="61px"
                toto[i].style.width="80px"
                svg = toto[i].getElementsByTagName("svg")[0]
                svg.setAttribute("width", 80)
                svg.setAttribute("height", 80)
                circle = svg.getElementsByTagName("circle")[0]
                circle.setAttribute("r", 35)
                circle.setAttribute("cx", 40)
                circle.setAttribute("cy", 40)
            }
        },

        debugOff: function (){
            debugStatus = "debugOFF"

            toto = document.getElementsByClassName('debug')
            for (i = 0 ; i < toto.length; i++ ){
                toto[i].className.baseVal='debug debugOFF'
            }
            document.getElementById('remain').className ='whiteblock debugOFF'
            document.getElementById('tileId').className ='whiteblock debugOFF'
            document.getElementById('tilePlayed').className ='whiteblock debugOFF'
            document.getElementById('tilePlayer').className ='whiteblock debugOFF'

            toto = document.getElementsByClassName('token')
            for (i = 0 ; i < toto.length; i++ ){
                toto[i].style.top="67px"
                toto[i].style.left="82px"
                toto[i].style.width=this.token_size+"px"
                svg = toto[i].getElementsByTagName("svg")[0]
                svg.setAttribute("witdh", this.token_size)
                svg.setAttribute("height", this.token_size)
                circle = svg.getElementsByTagName("circle")[0]
                circle.setAttribute("r", this.token_size/2-5)
                circle.setAttribute("cx", this.token_size/2)
                circle.setAttribute("cy", this.token_size/2)
            }
        },

        debugMenu: function (){
            this.statusBar.addActionButton(_('debug on'), () => this.debugOn(), { color: 'green' });
            this.statusBar.addActionButton(_('debug off'), () => this.debugOff(), { color: 'cyan' });
            this.statusBar.addActionButton(_('check place '), () => this.checkPlace(), { color: 'red' });
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function( stateName, args )
        {
            this.displayFinalRoundWarning();
            this.displayLowTileWarning();

            if(isDebug){
                this.debugMenu();
            }

            if( this.isCurrentPlayerActive() )
            {
                switch( stateName )
                {
                 case 'playerTurn':
                    this.statusBar.addActionButton(_('Confirm'), () => this.onPlay(), { color: 'primary' });
                    this.statusBar.addActionButton(_('Reset '), () => this.onReset(), { color: 'red' });
//                    this.statusBar.addActionButton(_('CAN NOT PLAY '), () => this.canNotPlay(), { color: 'red' });

                    this.refreshHandler();
                    break;

                 case 'chooseToken':
                    this.statusBar.addActionButton(_('Validate'), () => this.onValidate(), { color: 'primary' });
                    this.statusBar.addActionButton(_('Reset '), () => this.onReset(), { color: 'red' });
                    $("nbToken").innerHTML=this.nbToken;


                    this.statusBar.addActionButton(_('SKIP '), () => this.bgaPerformAction('actForcePass', {}), { color: 'red' });


                    this.disconnectAll();
                    this.connectClass("selectableToken" , 'onclick', 'selectTokenToRemove');
                //this.connectClass(this.playerId , 'onclick', 'onAction');

                    break;
                }
            }else{
                this.disconnectAll();
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.

        */




        selectPlace: function( place )
        {

            placeParent=place.parentNode
            placeId = placeParent.id
            playedTile = $(this.current_tile);
            createPlace = false
            try{

                x = Number(placeId.split("_")[1].split("x")[0]);
                y = Number(placeId.split("x")[1]);
            }catch{
                x = 0
                y = 0
            }


            if (playedTile != null){
                placeSvg = place.getElementsByTagName("svg")[0]
                playedTileSvg = playedTile.getElementsByTagName("svg")[0]

                placePolygon= placeSvg.getElementsByTagName("polygon")[0]
                playedTilePolygon = playedTileSvg.getElementsByTagName("polygon")[0]


                if  ( (playedTilePolygon.className.baseVal.match("handTile")) &&
                            (placePolygon.className.baseVal.match("commonPlace"))){
                    if(!playedTilePolygon.className.baseVal.match("savedTile")){
                        this.showMessage(_('Your tile can only go to the board'), 'error');
                        return
                    }
                    delete this.playerTile[playedTile.id]

                    playedTilePolygon.className.baseVal="handPlace"
                    placePolygon.className.baseVal="commonTile"

                }else if  (
                    (playedTilePolygon.className.baseVal.match("handTile") &&
                            placePolygon.className.baseVal.match("handPlace")) ||
                    (playedTilePolygon.className.baseVal.match("commonTile") &&
                            placePolygon.className.baseVal.match("commonPlace")) ||
                    (playedTilePolygon.className.baseVal.match("boardTile") &&
                            placePolygon.className.baseVal.match("boardPlace")) ){

                    if(placePolygon.className.baseVal.match("boardPlace")){
                        createPlace = true
                        delete (this.playedTile[playedTile.id])
                        this.playedTile[playedTile.id] = { x, y }
                    }

                    tmpClasse = playedTilePolygon.className.baseVal
                    playedTilePolygon.className.baseVal = placePolygon.className.baseVal
                    placePolygon.className.baseVal = tmpClasse

                }else if (
                    (playedTilePolygon.className.baseVal.match("handTile")) &&
                    (placePolygon.className.baseVal.match("boardPlace"))){

                    this.playedTile[playedTile.id] = { x , y}
                    if(playedTilePolygon.className.baseVal.match("savedTile")){
                        delete this.playerTile[playedTile.id]
                        placePolygon.className.baseVal="boardTile playedTile"
                    }else{
                        placePolygon.className.baseVal="handTile boardTile playedTile"
                    }
                    playedTilePolygon.className.baseVal="handPlace"

                    createPlace = true

                }else if  ( (playedTilePolygon.className.baseVal.match("commonTile")) &&
                        (placePolygon.className.baseVal.match("boardPlace"))){

                playedTilePolygon.className.baseVal="commonPlace"
                placePolygon.className.baseVal="boardTile playedTile"

                createPlace = true

                this.playedTile[playedTile.id] = { x , y}
                }else if(
                    (playedTilePolygon.className.baseVal.match("commonTile")) &&
                    (placePolygon.className.baseVal.match("handPlace"))){

                    this.playerTile[playedTile.id] = { x, y }
/*
                this.playerTile[playedTile.id] = this.playedTile[playedTile.id]
alert("delete common -> hand")
                delete this.playedTile[playedTile.id]
*/
                    playedTilePolygon.className.baseVal="commonPlace"
                    placePolygon.className.baseVal="handTile savedTile playedTile"

                }else if  ( (playedTilePolygon.className.baseVal.match("boardTile")) &&
                            (placePolygon.className.baseVal.match("commonPlace"))){

                    if(dojo.hasClass(playedTile,"purchasedTile")){
                        this.showMessage(_('purchased tile need to stay on board'), 'error');
                        return
                    }

                    delete this.playedTile[playedTile.id]

                    playedTilePolygon.className.baseVal="boardPlace"
                    placePolygon.className.baseVal="commonTile"


                }else if  ( (playedTilePolygon.className.baseVal.match("boardTile")) &&
                        (placePolygon.className.baseVal.match("handPlace"))){

                    if(dojo.hasClass(playedTile,"purchasedTile")){
                        this.showMessage(_('purchased tile need to stay on board'), 'error');
                        return
                    }

                    delete this.playedTile[playedTile.id]

                    placePolygon.className.baseVal="handTile"

                    if(!playedTilePolygon.className.baseVal.match("handTile")){
                        placePolygon.className.baseVal+=" playedTile savedTile"

                        this.playerTile[playedTile.id] = { x, y }
                    }

                    playedTilePolygon.className.baseVal="boardPlace"

                }else{
                    debug("*****************ERROR ************* ")
                    debug(playedTilePolygon.className)
                    debug(placePolygon.className)
                }

                text=""
                for( i in this.playedTile ){
                    text+=i+" "+this.playedTile[i]["x"]+" "+this.playedTile[i]["y"]+"<br/>"
                }
    $("tilePlayed").innerHTML="tile played:<br/>"+text

                text=""

                for( i in this.playerTile ){
                    text+=i+" "+this.playerTile[i]["x"]+" "+this.playerTile[i]["y"]+"<br/>"
                }

$("tilePlayer").innerHTML="tile player:<br/>"+text

            placeText= placeSvg.getElementsByTagName("text")[0]
            playedTileText = playedTileSvg.getElementsByTagName("text")[0]
            placeText2= placeSvg.getElementsByTagName("text")[2]
            playedTileText2 = playedTileSvg.getElementsByTagName("text")[2]

            fillTmp = playedTilePolygon.getAttribute("fill")
            fillOpacityTmp = playedTilePolygon.getAttribute("fill-opacity")
            strokeOpacityTmp = playedTilePolygon.getAttribute("stroke-fill")
            strokeTmp = playedTilePolygon.getAttribute("stroke")

            playedTilePolygon.setAttribute("fill", placePolygon.getAttribute("fill"))
            playedTilePolygon.setAttribute("fill-opacity", placePolygon.getAttribute("fill-opacity"))
            playedTilePolygon.setAttribute("stroke-opacity", placePolygon.getAttribute("stroke-opacity"))
            playedTilePolygon.setAttribute("stroke", placePolygon.getAttribute("stroke"))

            placePolygon.setAttribute("fill", fillTmp)
            placePolygon.setAttribute("fill-opacity", fillOpacityTmp)
            placePolygon.setAttribute("stroke-opacity", strokeOpacityTmp)
            placePolygon.setAttribute("stroke", strokeTmp)

            tmpId = place.id;
            place.id = playedTile.id
            playedTile.id = tmpId

            color=playedTile.getElementsByClassName("colorBlind")[0].innerHTML


/*
placeText.setAttribute("font-size",40)
playedTileText.setAttribute("font-size",40)
*/
tmp=playedTileText.innerHTML
playedTileText.innerHTML = placeText.innerHTML
placeText.innerHTML = tmp

tmp=playedTileText2.innerHTML
playedTileText2.innerHTML = placeText2.innerHTML
placeText2.innerHTML = tmp



            if( createPlace ){

                var possibleColor =[]
debug("color:"+color)

                possibleColor[0]= String((Number(color)+1)%6);
                possibleColor[1]= String((Number(color)+5)%6);
debug("possibleColor0:"+possibleColor[0])
debug("possibleColor1:"+possibleColor[1])

                r = Math.abs(x+y)%2

                var elements =[]

                elements[0]={x: x+1, y: y , possibleColor: possibleColor}
                elements[1]={x: x-1, y: y , possibleColor: possibleColor}

                if (r == 0){
                    elements[2]={x: x, y: y+1 , possibleColor: possibleColor}
                }else{
                    elements[2]={x: x, y: y-1 , possibleColor: possibleColor}
                }

                this.addElement(elements);

            }

            placeToDisactivate = document.getElementsByClassName(color)

            for (i = 0 ; i < placeToDisactivate.length; i++ ){
                if (!(placeToDisactivate[i].className.baseVal.includes("disabled")))
                    placeToDisactivate[i].className.baseVal=placeToDisactivate[i].className.baseVal+" disabled"

                blindNode=placeToDisactivate[i].parentNode.getElementsByClassName("colorBlind")[0]
                if (!(blindNode.className.baseVal.includes("disabled")))
                    blindNode.className.baseVal=blindNode.className.baseVal+" disabled"

            }

//            oldTile.removeClass("currentTile");


            this.refreshHandler();
            this.current_tile = "";

            }else{
                debug("not tile selected "+x+" "+y)
            }
        },

        // Change current tile
        selectTile: function( tile )
        {
            oldTile=dojo.query( ".currentTile" )

            if(oldTile.length != 0){
                color=oldTile[0].getElementsByClassName("colorBlind")[0].innerHTML

                placeToDisactivate = document.getElementsByClassName(color)
                for (i = 0 ; i < placeToDisactivate.length; i++ ){
                    if (!(placeToDisactivate[i].className.baseVal.includes("disabled")))
                        placeToDisactivate[i].className.baseVal=placeToDisactivate[i].className.baseVal+" disabled"
                    blindNode=placeToDisactivate[i].parentNode.getElementsByClassName("colorBlind")[0]
                    if (!(blindNode.className.baseVal.includes("disabled")))
                        blindNode.className.baseVal=blindNode.className.baseVal+" disabled"
                }

                oldTile.removeClass("currentTile");
            }

            this.current_tile = tile.id;

            color=tile.getElementsByClassName("colorBlind")[0].innerHTML
debug("selectTile Color:"+color)
            placeToActivate = document.getElementsByClassName(color)
            for (i = 0 ; i < placeToActivate.length; i++ ){
                placeToActivate[i].className.baseVal=placeToActivate[i].className.baseVal.replaceAll("disabled","")
                blindNode=placeToActivate[i].parentNode.getElementsByClassName("colorBlind")[0]
                blindNode.className.baseVal=blindNode.className.baseVal.replaceAll("disabled","")
            }


            dojo.query(tile).addClass("currentTile");  // Ex: "handtile2" => 2

            //this.updatePlaces();
        },

        // Change current tile
        selectToken: function( token )
        {

            dojo.query( ".currentToken" ).removeClass("currentToken");

            this.current_token = token.id;
            dojo.query(token).addClass("currentToken");  // Ex: "handtile2" => 2

        },

        selectTokenToRemove: function( evt ){
            token = evt.currentTarget

            tokenId =token.parentNode.parentNode.id.split("_")[1]

            if (token.className.baseVal.match("selectableToken")){
                token.className.baseVal=token.className.baseVal.replace("selectableToken", "selectedToken")
                this.nbToken--;
                this.token[tokenId]={tokenId}
            }else{
                token.className.baseVal=token.className.baseVal.replace("selectedToken","selectableToken")
                this.nbToken++;
                delete(this.token[tokenId])
            }

            $("nbToken").innerHTML=this.nbToken;
            this.disconnectAll();
            this.connectClass("selectableToken" , 'onclick', 'selectTokenToRemove');
            this.connectClass("selectedToken" , 'onclick', 'selectTokenToRemove');

        },

        buyTile: function (tile){
            currentToken = $(this.current_token);
            if (currentToken != null){
                currentToken.remove();

                svg = tile.getElementsByTagName("svg")[0]
                polygon= svg.getElementsByTagName("polygon")[0]
                polygon.className.baseVal="playedTile boardTile"//purchasedTile

dojo.query(tile).addClass("currentTile");

                placeId=tile.parentNode.id
                id=tile.id//.split("_")[1]

                this.current_tile = id;
                x = Number(placeId.split("_")[1].split("x")[0]);
                y = Number(placeId.split("x")[1]);


            color=tile.getElementsByClassName("colorBlind")[0].innerHTML
            placeToActivate = document.getElementsByClassName(color)
            for (i = 0 ; i < placeToActivate.length; i++ ){
                placeToActivate[i].className.baseVal=placeToActivate[i].className.baseVal.replace('disabled',"")

                blindNode=placeToActivate[i].parentNode.getElementsByClassName("colorBlind")[0]
                blindNode.className.baseVal=blindNode.className.baseVal.replace('disabled',"")

            }

                this.playedTile[id] = { x, y }

                tokenId=this.current_token.split("_")[1]

                this.token[tokenId]={id}

                this.refreshHandler();

            }else{
                this.showMessage(_("you need to select a token first"), 'error');
            }
        },

        onAction: function (evt){
            actionItem = evt.currentTarget//.parentNode.parentNode

            item=actionItem.parentNode.parentNode

            className=actionItem.className.baseVal

            if( className.match("Place") ){
                this.selectPlace(item)
            }else if (className.match("purchasableTile")){
                this.buyTile(item)
            }else if (className.match("Tile")){
                this.selectTile(item)
            }else if (item.className.match("token")){
                this.selectToken(item)
            }else{
                debug("************** error ***************")
debug(item)
            }
    },



        ///////////////////////////////////////////////////
        //// Player's action

        /*

            Here, you are defining methods to handle player's action (ex: results of mouse click on
            game objects).

            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server

        */

        // Example:


        onReset: function (){
             location.reload();
        },

        onValidate: function (){
            if(this.nbToken>0){
                this.showMessage(_('You still need to remove '+this.nbToken+' token'), 'error');
            }else if(this.nbToken<0){
                this.showMessage(_('You remove '+Math.abs(this.nbToken)+' extra token '), 'error');
            }else{
                token = ""
                for (var id in this.token ) {
                     token += id+";"
                }

                this.bgaPerformAction('actToken', {
                    token: token
                });
            }
        },

        onPlay: function () {

            tilePlayed = ""
            for (var id in this.playedTile ) {
                tile=this.playedTile[id]
                tilePlayed += id+","+tile.x+","+tile.y+";"
            }

            tilePlayer = ""
            for (var id in this.playerTile ) {
                tile=this.playerTile[id]
                tilePlayer += id+";"
            }

            tokenSpent = ""
            for (var id in this.token ) {
                token=this.token[id]
                tokenSpent += id+","+token.id+";"
            }

            this.bgaPerformAction('actPlay', {
                tilePlayed: tilePlayed,
                tilePlayer: tilePlayer,
                tokenSpent: tokenSpent
            });

        },

        canNotPlay: function () {
            this.bgaPerformAction('actCanNotPlay');
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your vizia.game.php file.

        */
        setupNotifications: function()
        {
            debug( 'notifications subscriptions setup' );

            this.bgaSetupPromiseNotifications();

        },

        notif_debug: function(args) {
            debug(args)
        },

        notif_playedTile: function(args) {
debug("played")
debug(args)
            if(this.playerId != args.player_id){

                this.addElement(args.tiles);
                this.addElement(args.places);
            }else{
                for (var id in this.playedTile ) {
                    playedTile = $(id);
                    playedTileSvg = playedTile.getElementsByTagName("svg")[0]
                    playedTilePolygon = playedTileSvg.getElementsByTagName("polygon")[0]
                    playedTilePolygon.className.baseVal="boardTile"
                }
                for (var id in this.playerTile ) {
                    playedTile = $(id);
                    playedTileSvg = playedTile.getElementsByTagName("svg")[0]
                    playedTilePolygon = playedTileSvg.getElementsByTagName("polygon")[0]
                    playedTilePolygon.className.baseVal="handTile"
                }


                this.playedTile = {};
                this.playerTile = {};
                this.tokenSpent = {};
            }
        },

        notif_newToken: function(args) {
console.log(args)
            this.addToken(args.token);
            this.removePlayerToken(args.token);
        },

        captureToken: function(tokens) {
            var newToken = []

            for( i in tokens ){

                if( tokens[i].id != -1){
                    circle=document.getElementById("token_"+tokens[i].id).getElementsByTagName("svg")[0].getElementsByTagName("circle")[0]
                    circle.setAttribute("fill","#"+this.players[tokens[i].player].color)
                    playerId=circle.className.baseVal


                    tpl = {};
                    tpl.token_size= this.token_size;
                    tpl.x='j'
                    if ((this.purchase == 1 ) && (tokens[i].player == this.playerId))
                        tpl.class="clickableToken"
                    else
                        tpl.class=""

                    tpl.group=""
                    var player_token_div = $('tokenPlayer_'+tokens[i].player);
                    tpl.y=i
                    tpl.color="#"+this.players[tokens[i].player].color ;
                    tpl.id=i
                    tpl.player=playerId

                    dojo.place(jstpl_token_player_board(i,tpl), player_token_div );

                    circle.className.baseVal=tokens[i].player
                }else{
                    newToken.push(tokens[i])
                }
            }
            this.addToken(newToken);
            this.removePlayerToken(newToken);
        },

        notif_tokenToRemove: function (args) {
            this.nbToken= args.nbToken
            this.tokenToDecide(args.token)
        },

        notif_captureToken: function(args) {
            this.captureToken(args.token)
        },

        notif_getPurchasableTiles: function(args) {
            this.updatePurchasableTiles(args);
        },

        notif_removeToken: function (args){
        for( i in args.removeToken ){
            debug($(("token_"+args.removeToken[i])))
            $(("token_"+args.removeToken[i])).remove();
            }
    },

        notif_purchased: function (args){
            if(this.playerId != args.player_id){
                var placeToAdd = []
                for( i in args.tileToRemove ){
                    place=$(args.tileToRemove[i]).parentNode
                    x = Number(place.id.split("_")[1].split("x")[0]);
                    y = Number(place.id.split("x")[1]);

                    placeToAdd[i]={ x, y }
                    place.remove();

                    $((args.tokenToRemove[i])).remove();

                }
                this.addElement(placeToAdd);

            }else{
            }


        },

        notif_nextPlayer: function(args) {

            this.commonTile(args.commonTile)
            this.refreshHandler();
            this.tileRemain(args.tilesRemain);
        },


        notif_game_end_trigger: function(notif) {

            this.finalRound = 1;
            this.displayFinalRoundWarning();
        },

        notif_lowTile: function(notif) {

            this.lowTile = 1;
            this.displayLowTileWarning();
        },

        notif_debug: function(args) {
            debug(args)
        }
   });
});
