/**
 * Repeater UI for the "Create a poll" / "Create a quiz" / "Create a form"
 * admin screens. Plain event delegation (no build step) so it works for
 * rows added after page load too.
 */
( function () {
	'use strict';

	var questionCounter = 1; // 0 is used by the first, PHP-rendered question.
	var fieldCounter = 1; // 0 is used by the first, PHP-rendered field.

	function closest( el, selector ) {
		return el.closest ? el.closest( selector ) : null;
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( target.classList.contains( 'qnaapi-connect-add-option' ) ) {
			event.preventDefault();
			addPollOption( target );
		} else if ( target.classList.contains( 'qnaapi-connect-remove-option' ) ) {
			event.preventDefault();
			removeRow( target, 'qnaapi-connect-poll-options' );
		} else if ( target.classList.contains( 'qnaapi-connect-add-question' ) ) {
			event.preventDefault();
			addQuizQuestion( target );
		} else if ( target.classList.contains( 'qnaapi-connect-remove-question' ) ) {
			event.preventDefault();
			var fieldset = closest( target, '.qnaapi-connect-question' );
			if ( fieldset ) {
				fieldset.remove();
			}
		} else if ( target.classList.contains( 'qnaapi-connect-add-choice' ) ) {
			event.preventDefault();
			addQuizChoice( target );
		} else if ( target.classList.contains( 'qnaapi-connect-remove-choice' ) ) {
			event.preventDefault();
			removeRow( target, null );
		} else if ( target.classList.contains( 'qnaapi-connect-add-field' ) ) {
			event.preventDefault();
			addFormField();
		} else if ( target.classList.contains( 'qnaapi-connect-remove-field' ) ) {
			event.preventDefault();
			var fieldFieldset = closest( target, '.qnaapi-connect-field' );
			if ( fieldFieldset ) {
				fieldFieldset.remove();
			}
		}
	} );

	function removeRow( button, minContainerId ) {
		var row = closest( button, '.qnaapi-connect-repeater-row' );

		if ( ! row ) {
			return;
		}

		var container = row.parentElement;

		// Keep at least one row so the form always has something to submit.
		if ( minContainerId && container && container.children.length <= 1 ) {
			return;
		}

		row.remove();
	}

	function addPollOption( button ) {
		var container = document.getElementById( 'qnaapi-connect-poll-options' );

		if ( ! container ) {
			return;
		}

		var index = container.children.length + 1;

		var row = document.createElement( 'p' );
		row.className = 'qnaapi-connect-repeater-row';
		row.innerHTML =
			'<input type="text" name="options[]" class="regular-text" placeholder="Option ' +
			index +
			'" required />' +
			'<button type="button" class="button-link-delete qnaapi-connect-remove-option">Remove</button>';

		container.appendChild( row );
		row.querySelector( 'input' ).focus();
	}

	function addQuizQuestion() {
		var template = document.getElementById( 'qnaapi-connect-question-template' );
		var container = document.getElementById( 'qnaapi-connect-quiz-questions' );

		if ( ! template || ! container ) {
			return;
		}

		var html = template.innerHTML.split( '__INDEX__' ).join( String( questionCounter ) );
		questionCounter += 1;

		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = html.trim();

		container.appendChild( wrapper.firstElementChild );
	}

	function addQuizChoice( button ) {
		var fieldset = closest( button, '.qnaapi-connect-question' );
		var choicesContainer = fieldset ? fieldset.querySelector( '.qnaapi-connect-choices' ) : null;

		if ( ! choicesContainer ) {
			return;
		}

		var questionNameMatch = ( choicesContainer.querySelector( 'input[type="text"]' ) || { name: '' } ).name.match( /questions\[([^\]]+)\]/ );
		var questionIndex = questionNameMatch ? questionNameMatch[ 1 ] : '0';
		var choiceIndex = parseInt( choicesContainer.getAttribute( 'data-next-choice-index' ), 10 ) || 0;

		var row = document.createElement( 'p' );
		row.className = 'qnaapi-connect-repeater-row';
		row.innerHTML =
			'<label><input type="checkbox" name="questions[' +
			questionIndex +
			'][choices][' +
			choiceIndex +
			'][correct]" value="1" /> Correct</label> ' +
			'<input type="text" name="questions[' +
			questionIndex +
			'][choices][' +
			choiceIndex +
			'][label]" class="regular-text" placeholder="Choice ' +
			( choiceIndex + 1 ) +
			'" required /> ' +
			'<button type="button" class="button-link-delete qnaapi-connect-remove-choice">Remove</button>';

		choicesContainer.appendChild( row );
		choicesContainer.setAttribute( 'data-next-choice-index', String( choiceIndex + 1 ) );
		row.querySelector( 'input[type="text"]' ).focus();
	}

	function addFormField() {
		var template = document.getElementById( 'qnaapi-connect-field-template' );
		var container = document.getElementById( 'qnaapi-connect-form-fields' );

		if ( ! template || ! container ) {
			return;
		}

		var html = template.innerHTML.split( '__INDEX__' ).join( String( fieldCounter ) );
		fieldCounter += 1;

		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = html.trim();

		container.appendChild( wrapper.firstElementChild );
	}
} )();
