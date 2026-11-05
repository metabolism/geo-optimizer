(function ($) {

	function checkConnection() {

		const $statusBadge = $('#geo-llm-status-badge.is-checking');

		if ( !$statusBadge.length )
			return;

		$.ajax({
			url: geo_optimizer.ajax_url,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'geo_opt_check_connection',
				nonce: geo_optimizer.nonce
			},
			success: function (response) {
				if (response.success) {
					$statusBadge
						.removeClass('is-checking is-error is-warning')
						.addClass('is-configured')
						.attr('title', response.data.message || 'Connexion active')
						.html('Connecté à '+response.data.provider);
				} else {
					const errMsg = (response.data && response.data.message) ? response.data.message : 'Échec de connexion';
					$statusBadge
						.removeClass('is-checking is-configured')
						.addClass('is-error')
						.attr('title', errMsg)
						.html('Erreur Provider');
				}
			},
			error: function (xhr, status, error) {
				$statusBadge
					.removeClass('is-checking is-configured')
					.addClass('is-error')
					.attr('title', 'Erreur réseau ou réponse invalide : ' + error)
					.html('Erreur réseau');
			}
		});
	}

	function handleGeneration() {

		/* ==========================================================================
           1. SÉLECTIONS D'ÉLÉMENTS DU DOM (METABOX)
           ========================================================================== */
		const $btnGenerate       = $('#btn-geo-trigger-generation');
		const $spinner           = $('#geo-generation-spinner');
		const $customPrompt      = $('#geo_custom_prompt');
		const $optimizeSEO       = $('#geo_update_seo_meta');
		const $rewriteBlocs      = $('#geo_update_rewrite_blocs');
		const $btnValidateReview = $('#btn-geo-validate-review');

		// Sécurité : Vérifier que l'objet global geoOptData est bien injecté
		if (typeof geo_optimizer === 'undefined')
			return;

		/* ==========================================================================
	   2. DÉCLENCHEMENT DE LA GÉNÉRATION (RAG / BEDROCK)
	   ========================================================================== */
		$btnGenerate.on('click', function (e) {
			e.preventDefault();

			// Récupération de l'ID du post (soit via geoOptData, soit via le champ natif WP)
			const postId = geo_optimizer.post_id || $('#post_ID').val();
			const customInstruction = $customPrompt.val() ? $customPrompt.val().trim() : '';

			if (!postId) {
				alert('Impossible d\'identifier le post en cours d\'édition.');
				return;
			}

			// UI : Passage en état chargement
			$btnGenerate.prop('disabled', true);
			$spinner.show();

			$.ajax({
				url: geo_optimizer.ajax_url,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'geo_opt_generate',
					nonce: geo_optimizer.nonce,
					post_id: postId,
					custom_instruction: customInstruction,
					update_seo_meta: $optimizeSEO.is(':checked'),
					rewrite_blocs: $rewriteBlocs.is(':checked')
				},
				success: function (response) {
					$btnGenerate.prop('disabled', false);
					$spinner.hide();

					if (response.success) {
						window.location.reload();
					} else {
						const errMsg = (response.data && response.data.message)
							? response.data.message
							: (response.data || 'Erreur inconnue lors de la génération.');
						alert('Échec de la génération : ' + errMsg);
					}
				},
				error: function (xhr, status, error) {
					$btnGenerate.prop('disabled', false);
					$spinner.hide();
					alert('Erreur réseau ou réponse serveur invalide : ' + error);
				}
			});
		});

		$btnValidateReview.on('click', function (e) {

			e.preventDefault();

			// Récupération de l'ID du post (soit via geoOptData, soit via le champ natif WP)
			const postId = geo_optimizer.post_id || $('#post_ID').val();

			if (!postId) {
				alert('Impossible d\'identifier le post en cours d\'édition.');
				return;
			}

			// UI : Passage en état chargement
			$btnValidateReview.prop('disabled', true).addClass('updating-message');
			$spinner.show();

			$.ajax({
				url: geo_optimizer.ajax_url,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'geo_opt_validate',
					nonce: geo_optimizer.nonce,
					post_id: postId
				},
				success: function (response) {

					$btnValidateReview.prop('disabled', false).removeClass('updating-message');
					$spinner.hide();

					if (response.success) {
						window.location.reload();
					} else {
						const errMsg = (response.data && response.data.message)
							? response.data.message
							: (response.data || 'Erreur inconnue lors de la génération.');
						alert('Échec de la génération : ' + errMsg);
					}
				},
				error: function (xhr, status, error) {
					$btnValidateReview.prop('disabled', false).removeClass('updating-message');
					$spinner.hide();
					alert('Erreur réseau ou réponse serveur invalide : ' + error);
				}
			});
		});
	}

	function handleBlockClick(){

		$(document).on('click', '[data-target-block]', function (e) {
			e.preventDefault();

			if (!window.wp || !wp.data || !wp.data.select('core/block-editor')) {
				return;
			}

			const targetBlockName = $(this).data('target-block');
			if (!targetBlockName) {
				return;
			}

			const { getBlocks } = wp.data.select('core/block-editor');
			const { selectBlock } = wp.data.dispatch('core/block-editor');

			// Recherche récursive du bloc dans l'arbre Gutenberg
			function findTargetClientId(blocks) {
				for (let i = 0; i < blocks.length; i++) {
					if (blocks[i].name === targetBlockName) {
						return blocks[i].clientId;
					}
					if (blocks[i].innerBlocks && blocks[i].innerBlocks.length > 0) {
						const found = findTargetClientId(blocks[i].innerBlocks);
						if (found) return found;
					}
				}
				return null;
			}

			const targetClientId = findTargetClientId(getBlocks());

			if (targetClientId) {
				// 1. Sélectionne le bloc dans Gutenberg (affiche son interface et sa toolbar)
				selectBlock(targetClientId);

				// 2. Défilement fluide jusqu'à l'élément dans l'éditeur
				setTimeout(function () {
					const blockElement = document.getElementById('block-' + targetClientId);
					if (blockElement) {
						blockElement.scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});
					}
				}, 50);
			} else {
				alert('Le bloc n\'a pas été trouvé sur la page courante.');
			}
		});
	}

	$(document).ready(function() {

		checkConnection();
		handleGeneration();
		handleBlockClick();
	});
})(jQuery);
