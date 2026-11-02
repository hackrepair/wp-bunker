/* The Hack Repair Guy's Bunker: instant-save checkboxes with a rotating message. */
( function () {
    'use strict';

    var config = window.thrgBunker || {};
    var live = document.getElementById( 'bunker-live' );
    var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

    function announce( text ) {
        if ( live && text ) {
            live.textContent = '';
            live.textContent = text;
        }
    }

    function setText( card, text ) {
        var message = card.querySelector( '.bunker-msg' );
        if ( ! message ) {
            return;
        }
        if ( reduced ) {
            message.textContent = text;
            return;
        }
        card.classList.add( 'is-flipping' );
        window.setTimeout( function () {
            message.textContent = text;
            card.classList.remove( 'is-flipping' );
        }, 150 );
    }

    function render( card, data ) {
        var check = card.querySelector( '.bunker-check' );
        var good = data.on && ! data.pending;
        check.checked = !! data.on;
        card.classList.toggle( 'is-on', good );
        card.classList.toggle( 'is-off', ! good );
        setText( card, data.text );
        announce( data.message ? data.message + ' ' + data.text : data.text );
        var note = card.querySelector( '.bunker-note' );
        if ( data.message ) {
            if ( ! note ) {
                note = document.createElement( 'p' );
                note.className = 'bunker-note';
                card.appendChild( note );
            }
            note.textContent = data.message;
        } else if ( note ) {
            note.remove();
        }
    }

    function save( card, checkbox ) {
        var body = new window.URLSearchParams();
        body.append( 'action', 'thrg_bunker_toggle' );
        body.append( 'nonce', config.nonce );
        body.append( 'id', card.getAttribute( 'data-id' ) );
        body.append( 'on', checkbox.checked ? '1' : '0' );
        checkbox.disabled = true;
        window.fetch( config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        } ).then( function ( response ) {
            return response.json();
        } ).then( function ( result ) {
            checkbox.disabled = false;
            if ( ! result || ! result.success ) {
                checkbox.checked = ! checkbox.checked;
                announce( ( result && result.data && result.data.message ) || config.error );
                return;
            }
            render( card, result.data );
        } ).catch( function () {
            checkbox.disabled = false;
            checkbox.checked = ! checkbox.checked;
            announce( config.error );
        } );
    }

    document.querySelectorAll( '.bunker-card' ).forEach( function ( card ) {
        var checkbox = card.querySelector( '.bunker-check' );
        checkbox.addEventListener( 'change', function () {
            save( card, checkbox );
        } );
    } );

    document.querySelectorAll( '.bunker-copy' ).forEach( function ( button ) {
        button.addEventListener( 'click', function () {
            var field = document.getElementById( button.getAttribute( 'data-target' ) );
            if ( ! field ) {
                return;
            }
            field.focus();
            field.select();
            if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
                window.navigator.clipboard.writeText( field.value );
            } else {
                document.execCommand( 'copy' );
            }
            announce( config.copied );
        } );
    } );
}() );
