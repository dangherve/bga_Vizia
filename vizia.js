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

const jstpl_triangle = (tpl) => `
<div id="tile_${tpl.tile_id}" class="${tpl.class} ${tpl.mapClass}">
    <svg width='${tpl.tile_size}px' viewBox="0 0 400 350" ${tpl.class} >
        <polygon
          points="${tpl.points[0][0]},${tpl.points[0][1]} ${tpl.points[1][0]},${tpl.points[1][1]} ${tpl.points[2][0]},${tpl.points[2][1]}"
          stroke="`+tpl.color+`" stroke-width=10 stroke-opacity="`+tpl.opacity+`"
          fill="`+tpl.color+`" fill-opacity="`+tpl.opacity+`" />
        <text class="debug ${debugStatus} " x="${tpl.coord[0]}" y="${tpl.coord[1]-25}" font-size="60" fill="${tpl.coord[2]}">${tpl.tile_id}</text>
        <text class="debug ${debugStatus} " x="${tpl.coord[0]}" y="${tpl.coord[1]+25}" font-size="60" fill="${tpl.coord[2]}">${tpl.x}x${tpl.y}</text>
        <text class="colorBlind ${colorBlindStatus} " x="${tpl.coord[0]+25}" y="${tpl.coord[1]}" font-size="150" fill="${tpl.coord[2]}">${tpl.colorText}</text>
    </svg>
</div>`;


const jstpl_circle = (tpl) => `
<div class='token ${tpl.player}' id="token_${tpl.id}" style="width:${tpl.token_size}px;top:${tpl.top}px;left:${tpl.left}px;">
<svg  width='${tpl.token_size}' height='${tpl.token_size}' >
<circle r="${tpl.token_size/2-5}" cx="${tpl.token_size/2}" cy="${tpl.token_size/2}" opacity="1" fill="${tpl.color}" />
<text style="display: none; z-index: 10" x="10" y="40" font-size="30" fill="black">${tpl.x}x${tpl.y}</text>
</svg>
</div>`;

const jstpl_element_on_map = (tpl) => `
<div id="place_${tpl.x}x${tpl.y}" class="map" style="top:${tpl.top}px;left:${tpl.left}px;">`
    +jstpl_triangle(tpl)+
`</div>`

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/scrollmap"
],
function (dojo, declare) {
    return declare("bgagame.vizia", ebg.core.gamegui, {
        constructor: function(){
            debug('vizia constructor');

            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
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
                1: [130,175,"black"],
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
            this.commonTile = {}
            this.token =  {}

            this.handler = {}

            this.final_round = 0

            this.tmpId=1000;

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
                <div id="remain" class="whiteblock"></div>
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
<!--
                <div id="carcafooter" class="whiteblock">
                    <a href="#" id="shrinkdisplay">^&nbsp;&nbsp;{LB_SHRINK_DISPLAY}&nbsp;&nbsp;^</a>
                    <a href="#" id="enlargedisplay">v&nbsp;&nbsp;{LB_ENLARGE_DISPLAY}&nbsp;&nbsp;v</a>
                </div>
-->
            `);

            this.scrollmap = new ebg.scrollmap(); // declare an object (this can also go in constructor)
            // Make map scrollable
            this.scrollmap.create( $('map_container'),$('map_scrollable'),$('map_surface'),$('map_scrollable_oversurface') ); // use ids from template
            this.scrollmap.setupOnScreenArrows( 150 ); // this will hook buttons to onclick functions with 150px scroll step

        },


        CommonTile: function( common){


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
                }else{
                    tpl.tile_id=this.tmpId;
                    this.tmpId++;
                    tpl.class="boardPlace";
                    tpl.color=this.tileColor[this.colorSection][6];
                    tpl.colorText=6
                    tpl.opacity=0.2;
                    playable=true
                }

                //get current element
                element="place_"+tpl.x+"x"+tpl.y
                htmlelement=document.getElementById(element)

                //if not exist just add it
                if (htmlelement == null){
                    elementToAdd = jstpl_element_on_map( tpl )
                    $('map_scrollable_oversurface').innerHTML += elementToAdd;

                }else{

                    //check if we have to replace a place by a tile
                    if (tpl.class == "boardTile"){

try{
                        htmlelement.parentNode.removeChild(htmlelement)

                        elementToAdd = jstpl_element_on_map( tpl )
                        $('map_scrollable_oversurface').innerHTML += elementToAdd;
}catch(err){
if(isDebug)
alert("*** check dom ****")
}

                    }
                    //do nothing if otherwise
                }

            }

            this.refreshHandler();

        },

        addToken: function (tokens){

            for( i in tokens ){
                var token = tokens[i];
                if(token.player != null){

                    tpl = {};

                    tpl.top=67;
                    tpl.left=82;
                    tpl.token_size= this.token_size;

                    tpl.class="token";
                    tpl.color="#"+this.players[token.player].color ;
                    tpl.id=token.id
                    tpl.player=token.player

                    document.getElementById("place_"+token.x+"x"+token.y).innerHTML += jstpl_circle(tpl);

                }
            }

        },


        displayFinalRoundWarning: function() {
            if (this.final_round == 1) {
                $('generalactions').innerHTML = '<div id="final_round"></div>';
                $('final_round').innerHTML = _('Warning: This is the final round');
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
                tpl.tile_size = this.tile_sizeWPrivate;


                tpl.y=1;
                tpl.points=this.tileRotate[0];
                tpl.coord=this.tileCoord[0];

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
                colorBlindStatus = "colorBlindOFF"
            }else{
                colorBlindStatus = "colorBlindON"
            }
            for (i = 0 ; i < elementToChange.length; i++ ){
                elementToChange[i].className.baseVal='colorBlind '+colorBlindStatus
            }
        },

        onColorChanged: function (pref_value) {
            this.colorSection = pref_value;
        },

        updatePurchasableTiles: function(tiles){
            dojo.query( ".purchasableTile" ).removeClass("purchasableTile");
            for( i in tiles ){
                dojo.addClass("tile_"+tiles[i],"purchasableTile");
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

        setup: function( gamedatas )
        {
            debug( "Starting game setup" );

            this.onColorChanged(this.getGameUserPreference(102))

            this.purchase = gamedatas.purchase


            this.playerId = Number(gamedatas.player_id);

            this.players = gamedatas.players;

            this.initiateTemplate();

            this.CommonTile(gamedatas.common);
            this.initiatePlayer(gamedatas.hand);

            this.addElement(gamedatas.places);
            this.addElement(gamedatas.tiles);

            this.updatePurchasableTiles(gamedatas.purchasableTiles)

            this.addToken(gamedatas.token);

            this.final_round=gamedatas.final_round
            this.displayFinalRoundWarning();

            document.getElementById('remain').innerHTML= gamedatas.tilesRemain;

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            this.refreshHandler();
            debug( "Ending game setup" );
        },


        ///////////////////////////////////////////////////
        //// Interface action


        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            debug( 'Entering state: '+stateName, args );

            switch( stateName )
            {

            /* Example:

            case 'myGameState':

                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );

                break;
           */


            case 'dummy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            debug( 'Leaving state: '+stateName );

            switch( stateName )
            {

            /* Example:

            case 'myGameState':

                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );

                break;
           */


            case 'dummy':
                break;
            }
        },

        debugOn: function (){
            toto = document.getElementsByClassName('debug')
            for (i = 0 ; i < toto.length; i++ ){
                toto[i].className.baseVal='debug debugON'
            }
            document.getElementById('remain').className ='whiteblock debugON'
        },
        debugOff: function (){
            toto = document.getElementsByClassName('debug')
            for (i = 0 ; i < toto.length; i++ ){
                toto[i].className.baseVal='debug debugOFF'
            }
            document.getElementById('remain').className ='whiteblock debugOFF'
        },


        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function( stateName, args )
        {
            debug( 'onUpdateActionButtons: '+stateName, args );

            this.displayFinalRoundWarning();

            if(isDebug){
                this.statusBar.addActionButton(_('debug on'), () => this.debugOn(), { color: 'green' });
                this.statusBar.addActionButton(_('debug off'), () => this.debugOff(), { color: 'cyan' });
            }

            if( this.isCurrentPlayerActive() )
            {
                switch( stateName )
                {
                 case 'playerTurn':
                    this.statusBar.addActionButton(_('Play'), () => this.onPlay(), { color: 'primary' });
                    this.statusBar.addActionButton(_('Reset '), () => this.onReset(), { color: 'red' });

                    this.refreshHandler();
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


        updatePlaces: function() {
/*
             dojo.query('.boardPlace').forEach( dojo.hitch( this, function( node ) {
                    dojo.fadeOut( {node: node } ).play();
             } ) );
            dojo.query('.boardPlace').removeClass('disabled');

            dojo.query('.boardPlace').addClass('enabled');

            var current_tile = this.gamedatas.common[ this.current_tile_no ];
            if (current_tile != null) {
                dojo.query('.place').forEach( dojo.hitch( this, function( node ) {
                    var target_left = dojo.style( node, "left");
                    var target_top = dojo.style( node, "top");
                    var x = Math.round( target_left/this.tile_size );
                    var y = Math.round( target_top/this.tile_size )
                    var valid = false;
                    for (var a=1; a<=4; a++) {
                        valid = valid;
                    }
                    if (valid) {
                        dojo.removeClass(node, 'disabled');
                        dojo.fadeIn( {node: node } ).play();
                    }
                 } ) );
             }
*/
        },



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

            if  ((dojo.hasClass(playedTile,"handTile") && dojo.hasClass(place,"commonPlace"))){

                if(!dojo.hasClass(playedTile,"playedTile")){
                    this.showMessage(_('Your tile can only go to the board'), 'error');
                    return
                }
                delete this.playerTile[playerTile.id]

                dojo.removeClass(place, "commonPlace")
                dojo.addClass(place, "commonTile")

                dojo.removeClass(playedTile, "handTile")
                dojo.removeClass(playedTile, "playedTile")
                dojo.addClass(playedTile, "handPlace")

            }else if  ((dojo.hasClass(playedTile,"handTile") && dojo.hasClass(place,"boardPlace"))){
                this.playedTile[playedTile.id] = { x , y}
                delete this.playerTile[playedTile.id]

                dojo.removeClass(place, "boardPlace")
                dojo.addClass(place, "boardTile")
                dojo.addClass(place, "playedTile")
                dojo.addClass(place, "handTile")

                dojo.removeClass(playedTile, "handTile")
                dojo.addClass(playedTile, "handPlace")
                createPlace = true

            }else if  ((dojo.hasClass(playedTile,"commonTile") && dojo.hasClass(place,"boardPlace"))){
                dojo.removeClass(place, "boardPlace")
                dojo.addClass(place, "boardTile")
                dojo.addClass(place, "playedTile")

                dojo.removeClass(playedTile, "commonTile")
                dojo.addClass(playedTile, "commonPlace")
                createPlace = true

                this.playedTile[playedTile.id] = { x , y}
            }else if  ((dojo.hasClass(playedTile,"commonTile") && dojo.hasClass(place,"handPlace"))){

                this.playerTile[playedTile.id] = this.playedTile[playedTile.id]
                delete this.playedTile[playedTile.id]

                dojo.removeClass(place, "handPlace")
                dojo.addClass(place, "playedTile")
                dojo.addClass(place, "handTile")

                dojo.removeClass(playedTile, "commonTile")
                dojo.addClass(playedTile, "commonPlace")
                dojo.removeClass(playedTile, "playedTile")

            }else if  ((dojo.hasClass(playedTile,"boardTile") && dojo.hasClass(place,"commonPlace"))){

                if(!dojo.hasClass(playedTile,"purchasedTile")){
                    this.showMessage(_('purchased tile need to stay on board'), 'error');
                    return
                }

                delete this.playedTile[playedTile.id]

                dojo.removeClass(place, "commonPlace")
                dojo.addClass(place, "commonTile")

                dojo.removeClass(playedTile, "playedTile")
                dojo.removeClass(playedTile, "boardTile")
                dojo.addClass(playedTile, "boardPlace")

            }else if  ((dojo.hasClass(playedTile,"boardTile") && dojo.hasClass(place,"handPlace"))){

                if(!dojo.hasClass(playedTile,"purchasedTile")){
                    this.showMessage(_('purchased tile need to stay on board'), 'error');
                    return
                }

                    delete this.playedTile[playedTile.id]

                    dojo.removeClass(place, "handPlace")
                    dojo.addClass(place, "handTile")

                    if(!dojo.hasClass(playedTile,"handTile")){
                         dojo.addClass(place, "playedTile")
                        this.playerTile[playedTile.id] = { x, y }
                    }

                    dojo.removeClass(playedTile, "handTile")
                    dojo.removeClass(playedTile, "playedTile")
                    dojo.removeClass(playedTile, "boardTile")
                    dojo.addClass(playedTile, "boardPlace")

            }else if (
                (dojo.hasClass(playedTile,"handTile") && dojo.hasClass(place,"handPlace")) ||
                (dojo.hasClass(playedTile,"commonTile") && dojo.hasClass(place,"commonPlace")) ||
                (dojo.hasClass(playedTile,"boardTile") && dojo.hasClass(place,"boardPlace"))
                ){

                if(dojo.hasClass(place,"boardPlace")){
                    createPlace = true
                    this.playedTile[playedTile.id] = { x, y }
                }

                tmpClasse = playedTile.className
                playedTile.className = place.className
                place.className = tmpClasse


            }else{
            debug(playedTile.className)
            debug(place.className)

            }
            dojo.query( ".currentTile" ).removeClass("currentTile");

            placeSvg = place.getElementsByTagName("svg")[0]
            playedTileSvg = playedTile.getElementsByTagName("svg")[0]

            placePolygon= placeSvg.getElementsByTagName("polygon")[0]
            playedTilePolygon = playedTileSvg.getElementsByTagName("polygon")[0]

            placeText= placeSvg.getElementsByTagName("text")[0]
            playedTileText = playedTileSvg.getElementsByTagName("text")[0]

            fillTmp = playedTilePolygon.getAttribute("fill")
            fillOpacityTmp = playedTilePolygon.getAttribute("fill-opacity")

            playedTilePolygon.setAttribute("fill", placePolygon.getAttribute("fill"))
            playedTilePolygon.setAttribute("fill-opacity", placePolygon.getAttribute("fill-opacity"))

            placePolygon.setAttribute("fill", fillTmp)
            placePolygon.setAttribute("fill-opacity", fillOpacityTmp)

            tmpId = place.id;
            place.id = playedTile.id
            playedTile.id = tmpId

/*
placeText.setAttribute("font-size",40)
playedTileText.setAttribute("font-size",40)
*/
tmp=playedTileText.innerHTML
playedTileText.innerHTML = placeText.innerHTML
placeText.innerHTML = tmp

            if( createPlace ){

                r = Math.abs(x+y)%2

                var elements =[]

                elements[0]={x: x+1, y: y }
                elements[1]={x: x-1, y: y }

                if (r == 0){
                    elements[2]={x: x, y: y+1 }
                }else{
                    elements[2]={x: x, y: y-1 }
                }

                this.addElement(elements);

            }
            this.refreshHandler();
            this.current_tile = "";

            }else{
                debug("not tile selected "+x+" "+y)
            }
        },

        // Change current tile
        selectTile: function( tile )
        {

            dojo.query( ".currentTile" ).removeClass("currentTile");

            this.current_tile = tile.id;

            dojo.query(tile).addClass("currentTile");  // Ex: "handtile2" => 2

            //this.updatePlaces();
        },

        // Change current tile
        selectToken: function( token )
        {

            dojo.query( ".currentToken" ).removeClass("currentToken");

            this.current_token = token.id;
debug(token)
            dojo.query(token).addClass("currentToken");  // Ex: "handtile2" => 2

            //this.updatePlaces();
        },


        buyTile: function (tile){
            currentToken = $(this.current_token);
            if (currentToken != null){
                currentToken.remove();
                dojo.query(tile).removeClass("purchasableTile");
                dojo.query(tile).addClass("currentTile");
                dojo.query(tile).addClass("playedTile");

                placeId=tile.parentNode.id
                id=tile.id//.split("_")[1]

                this.current_tile = id;
                x = Number(placeId.split("_")[1].split("x")[0]);
                y = Number(placeId.split("x")[1]);

                this.playedTile[id] = { x, y }

                tokenId=this.current_token.split("_")[1]

                this.token[tokenId]={id}

                this.refreshHandler();

            }else{
                debug("not token selected")
            }
        },

        onAction: function (evt){
            item = evt.currentTarget//.parentNode.parentNode
debug(item.className)
            if( item.className.match("Place") ){
                this.selectPlace(item)
            }else if (item.className.match("purchasableTile")){
                this.buyTile(item)
            }else if (item.className.match("Tile")){
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

        onPlay: function () {

            tileCommon = ""
            for (var id in this.commonTile ) {
                tile=this.commonTile[id]
                tileCommon += id+";"
            }

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



debug(tilePlayed)
debug(tilePlayer)
debug(tileCommon)

            this.bgaPerformAction('actPlay', {
                tilePlayed: tilePlayed,
                tilePlayer: tilePlayer,
                tileCommon: tileCommon,
                tokenSpent: tokenSpent
            });

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

        notif_playedTile: function(args) {

            if(this.playerId != args.player_id){
console.log(args)
                this.addElement(args.tiles);
                this.addElement(args.places);
            }else{

                dojo.query('.playedTile').removeClass('playedTile');
                this.playedTile = {};
                this.playerTile = {};
                this.commonTile = {};
                this.tokenSpent = {};
            }
        },

        notif_newToken: function(args) {
            this.addToken(args.token);
        },

        notif_captureToken: function(args) {
console.log(args)
            if( args.token[0].id != -1){
                document.getElementById("token_"+args.token[0].id).getElementsByTagName("svg")[0].getElementsByTagName("circle")[0].setAttribute("fill","#"+this.players[args.token[0].player].color)
            }else{
                this.addToken(args.token);
            }
        },

        notif_getPurchasableTiles: function(args) {
            this.updatePurchasableTiles(args);
        },

        notif_purchased: function (args){
debug("***Purchased****")

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

debug("***END****")

        },

        notif_nextPlayer: function(args) {
            this.CommonTile(args.commonTile)
            this.refreshHandler();
            document.getElementById('remain').innerHTML= args.tilesRemain;
        },

        notif_game_end_trigger: function(notif) {

            this.final_round = 1;
            this.displayFinalRoundWarning();
        },


        notif_debug: function(args) {
            debug(args)
        }
   });
});
