/**
 * Handles the three public, no-API-key QNAAPI endpoints directly from the
 * browser: casting a vote, submitting a quiz attempt, and submitting a
 * form. Each widget's markup is rendered server-side by the shortcodes/
 * blocks class; this file only wires up the "submit" interaction.
 */
( function () {
	'use strict';

	var config = window.qnaapiConnect || { apiBase: 'https://qnaapi.com/api/v1', i18n: {} };

	function voterId() {
		var key = 'qnaapi_connect_voter_id';
		var id;

		try {
			id = window.localStorage.getItem( key );

			if ( ! id ) {
				id = 'wp-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2 );
				window.localStorage.setItem( key, id );
			}
		} catch ( e ) {
			// Storage unavailable (private mode, etc.) — fall back to a per-page id;
			// this only means the "already responded" shortcut below won't persist.
			id = 'wp-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2 );
		}

		return id;
	}

	function hasRespondedTo( type, id ) {
		try {
			return window.localStorage.getItem( 'qnaapi_connect_done_' + type + '_' + id ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	function markResponded( type, id ) {
		try {
			window.localStorage.setItem( 'qnaapi_connect_done_' + type + '_' + id, '1' );
		} catch ( e ) {
			// Nothing to do — see hasRespondedTo().
		}
	}

	function postJson( path, body ) {
		return window
			.fetch( config.apiBase + path, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( body ),
			} )
			.then( function ( response ) {
				return response.json().then( function ( json ) {
					return { ok: response.ok, status: response.status, body: json };
				} );
			} );
	}

	function captchaToken( form ) {
		// Present only when the widget rendered a Cloudflare Turnstile div
		// (i.e. the resource has requires_captcha set) — Turnstile injects
		// this hidden input as a child of that div once the challenge
		// completes.
		var tokenInput = form.querySelector( '[name="cf-turnstile-response"]' );
		return tokenInput ? tokenInput.value : null;
	}

	function setMessage( widget, text ) {
		var message = widget.querySelector( '.qnaapi-widget-message' );
		if ( message ) {
			message.textContent = text;
		}
	}

	/**
	 * The best available message for a failed submit: the specific
	 * "closed" validation error QNAAPI returns once a poll/quiz/form has
	 * been closed or capped out since this page was rendered (the widget
	 * itself is re-fetched server-side at most every 5 minutes, so a
	 * still-open-looking form can occasionally submit into one that just
	 * closed), falling back to the API's generic message, then a
	 * plugin-wide default.
	 */
	function errorMessage( result ) {
		var body = result && result.body;
		var closedError = body && body.errors && body.errors.closed;

		return ( closedError && closedError[ 0 ] ) || ( body && body.message ) || config.i18n.genericError || '';
	}

	/* ----------------------------- Polls ----------------------------- */

	function initPoll( widget ) {
		var form = widget.querySelector( '.qnaapi-poll-form' );
		var pollId = widget.getAttribute( 'data-qnaapi-id' );

		if ( ! form || ! pollId ) {
			return;
		}

		if ( hasRespondedTo( 'poll', pollId ) ) {
			showPollResults( widget );
			setMessage( widget, config.i18n.alreadyVoted || '' );
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var selected = form.querySelector( 'input[name="qnaapi_poll_option"]:checked' );

			if ( ! selected ) {
				return;
			}

			setMessage( widget, config.i18n.voting || '' );

			var body = {
				poll_option_id: parseInt( selected.value, 10 ),
				voter_identifier: voterId(),
			};
			var token = captchaToken( form );
			if ( token ) {
				body.captcha_token = token;
			}

			postJson( '/polls/' + pollId + '/votes', body ).then( function ( result ) {
				if ( result.ok || 409 === result.status ) {
					markResponded( 'poll', pollId );

					if ( result.ok ) {
						selected.setAttribute( 'data-votes', String( parseInt( selected.getAttribute( 'data-votes' ), 10 ) + 1 ) );
					}

					showPollResults( widget );
					setMessage( widget, 409 === result.status ? config.i18n.alreadyVoted || '' : config.i18n.voted || '' );
				} else {
					setMessage( widget, errorMessage( result ) );
				}
			} );
		} );
	}

	function showPollResults( widget ) {
		var form = widget.querySelector( '.qnaapi-poll-form' );
		var results = widget.querySelector( '.qnaapi-poll-results' );

		if ( ! results ) {
			return;
		}

		var options = Array.prototype.slice.call( widget.querySelectorAll( 'input[name="qnaapi_poll_option"]' ) );
		var total = options.reduce( function ( sum, option ) {
			return sum + parseInt( option.getAttribute( 'data-votes' ), 10 );
		}, 0 );

		results.innerHTML = '';

		options.forEach( function ( option ) {
			var votes = parseInt( option.getAttribute( 'data-votes' ), 10 );
			var percentage = total > 0 ? Math.round( ( votes / total ) * 100 ) : 0;
			var label = option.closest( 'label' );
			var labelText = label ? label.querySelector( '.qnaapi-poll-option-label' ).textContent : '';

			var row = document.createElement( 'div' );
			row.className = 'qnaapi-poll-result-row';

			var bar = document.createElement( 'div' );
			bar.className = 'qnaapi-poll-result-bar';
			bar.style.width = percentage + '%';

			var text = document.createElement( 'span' );
			text.className = 'qnaapi-poll-result-text';
			text.textContent = labelText + ' — ' + percentage + '% (' + votes + ')';

			row.appendChild( bar );
			row.appendChild( text );
			results.appendChild( row );
		} );

		if ( form ) {
			form.hidden = true;
		}
		results.hidden = false;
	}

	/* ----------------------------- Quizzes ----------------------------- */

	function initQuiz( widget ) {
		var form = widget.querySelector( '.qnaapi-quiz-form' );
		var quizId = widget.getAttribute( 'data-qnaapi-id' );

		if ( ! form || ! quizId ) {
			return;
		}

		if ( hasRespondedTo( 'quiz', quizId ) ) {
			form.hidden = true;
			setMessage( widget, config.i18n.quizAlreadyDone || '' );
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var answers = Array.prototype.map.call( widget.querySelectorAll( '.qnaapi-quiz-question' ), function ( fieldset ) {
				var questionId = parseInt( fieldset.getAttribute( 'data-question-id' ), 10 );
				var checked = Array.prototype.slice.call( fieldset.querySelectorAll( 'input:checked' ) );

				return {
					question_id: questionId,
					choice_ids: checked.map( function ( input ) {
						return parseInt( input.value, 10 );
					} ),
				};
			} );

			if ( answers.some( function ( answer ) { return 0 === answer.choice_ids.length; } ) ) {
				setMessage( widget, config.i18n.genericError || '' );
				return;
			}

			setMessage( widget, config.i18n.quizSubmitting || '' );

			var body = {
				taker_identifier: voterId(),
				answers: answers,
			};
			var token = captchaToken( form );
			if ( token ) {
				body.captcha_token = token;
			}

			postJson( '/quizzes/' + quizId + '/attempts', body ).then( function ( result ) {
				if ( result.ok ) {
					markResponded( 'quiz', quizId );
					form.hidden = true;

					var resultBox = widget.querySelector( '.qnaapi-quiz-result' );
					if ( resultBox ) {
						var data = result.body.data || {};
						resultBox.textContent = data.score + ' / ' + data.total + ' (' + data.percentage + '%)';
						resultBox.hidden = false;
					}

					setMessage( widget, '' );
				} else if ( 409 === result.status ) {
					markResponded( 'quiz', quizId );
					form.hidden = true;
					setMessage( widget, config.i18n.quizAlreadyDone || '' );
				} else {
					setMessage( widget, errorMessage( result ) );
				}
			} );
		} );
	}

	/* ----------------------------- Forms ----------------------------- */

	function initForm( widget ) {
		var form = widget.querySelector( '.qnaapi-form-form' );
		var formId = widget.getAttribute( 'data-qnaapi-id' );

		if ( ! form || ! formId ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var answers = [];

			Array.prototype.forEach.call( widget.querySelectorAll( '.qnaapi-form-field' ), function ( fieldWrapper ) {
				var fieldId = parseInt( fieldWrapper.getAttribute( 'data-field-id' ), 10 );
				var type = fieldWrapper.getAttribute( 'data-field-type' );
				var value = readFieldValue( fieldWrapper, type );

				if ( null !== value && '' !== value && ! ( Array.isArray( value ) && 0 === value.length ) ) {
					answers.push( { field_id: fieldId, value: value } );
				}
			} );

			setMessage( widget, config.i18n.formSubmitting || '' );

			var body = {
				respondent_identifier: voterId(),
				answers: answers,
			};
			var token = captchaToken( form );
			if ( token ) {
				body.captcha_token = token;
			}

			postJson( '/forms/' + formId + '/submissions', body ).then( function ( result ) {
				if ( result.ok ) {
					form.hidden = true;
					setMessage( widget, config.i18n.formSubmitted || '' );
				} else {
					setMessage( widget, errorMessage( result ) );
				}
			} );
		} );
	}

	function readFieldValue( fieldWrapper, type ) {
		if ( 'multi_choice' === type ) {
			return Array.prototype.map.call(
				fieldWrapper.querySelectorAll( 'input[type="checkbox"]:checked' ),
				function ( input ) {
					return input.value;
				}
			);
		}

		var input = fieldWrapper.querySelector( 'input, select, textarea' );

		if ( ! input ) {
			return null;
		}

		if ( 'rating' === type ) {
			return input.value ? parseInt( input.value, 10 ) : null;
		}

		if ( 'number' === type ) {
			return '' === input.value ? null : parseFloat( input.value );
		}

		return input.value;
	}

	/* ----------------------------- Boot ----------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.qnaapi-poll' ).forEach( initPoll );
		document.querySelectorAll( '.qnaapi-quiz' ).forEach( initQuiz );
		document.querySelectorAll( '.qnaapi-form' ).forEach( initForm );
	} );
} )();
