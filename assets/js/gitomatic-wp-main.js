'use strict';

(function(j) {

j(function() {
	initDeploymentForm();
	init_dashboard_listenes();
	init_log_textarea_scroll();
	init_log_copy_button();
});

function initDeploymentForm() {
	const branch = document.querySelector('#repository_branch');
	if (!branch) return;
	const form = branch.form;
	const fields = form.elements;
	const provider = fields.provider_type;
	const repo = fields.repository_url;
	const folder = fields.folder;
	const load = document.querySelector('#gitomatic-wp-load-branches');
	const status = document.querySelector('#gitomatic-wp-branch-status');
	const choices = document.querySelector('#gitomatic-wp-branch-select');
	let page = 1;
	let request;
	let automaticFolder = !folder || !folder.value;

	function preview() {
		if (!folder) return;
		const shared = fields.destination_mode && fields.destination_mode.value === 'shared';
		const existing = fields.use_existing && fields.use_existing.checked;
		const kind = fields.package_type.value;
		if (fields.use_existing) {
			document.querySelector('#gitomatic-wp-existing').hidden = !existing;
			form.querySelectorAll('[data-existing]').forEach(label => {
				label.hidden = label.dataset.existing !== kind;
				const select = label.querySelector('select');
				select.disabled = !existing || label.hidden;
				select.required = !select.disabled;
			});
		}
		const base = folder.dataset.base;
		if (automaticFolder) {
			const suffix = branch.value.replace(/[^a-zA-Z0-9._-]+/g, '-');
			folder.value = base ? base + (suffix ? '-' + suffix : '') :
				repo.value.replace(/\/+$/, '').split('/').pop().replace(/\.git$/, '');
		}
		document.querySelector('#gitomatic-wp-folder').hidden = !!(shared || existing);
		folder.disabled = !!(shared || existing);
		folder.required = !folder.disabled;
		const destination = existing ? fields['existing_' + kind].value : (shared ? base : folder.value);
		document.querySelector('#gitomatic-wp-path').textContent = destination ?
			gitomatic_wp.roots[fields.package_type.value] + '/' + destination : '';
	}

	function refreshCredentials() {
		if (!provider) return;
		const auth = provider.value === 'bitbucket' ? 'bitbucket' : 'token';
		form.querySelectorAll('[data-auth]').forEach(label => {
			label.hidden = !fields.is_private_repository.checked || label.dataset.auth !== auth;
			label.querySelector('input').disabled = label.hidden;
		});
	}

	function resetBranches() {
		if (request) request.abort();
		page = 1;
		branch.hidden = false;
		branch.required = true;
		choices.replaceChildren();
		choices.hidden = true;
		const selectLabel = form.querySelector('label[for="gitomatic-wp-branch-select"]');
		if (selectLabel) selectLabel.htmlFor = 'repository_branch';
		choices.required = false;
		load.hidden = false;
		load.textContent = gitomatic_wp.load_branches;
		status.textContent = gitomatic_wp.manual_branch;
	}

	function loadBranches() {
		if (!fields.source_id && (!provider.value || !repo.validity.valid || !repo.value)) return;
		const params = new URLSearchParams();
		const data = new FormData(form);
		const keys = fields.source_id ? ['source_id', 'package_type'] :
			['repository_url', 'provider_type', 'package_type', 'is_private_repository', 'access_token', 'username', 'password'];
		keys.forEach(key => { if (data.has(key)) params.set(key, data.get(key)); });
		params.set('action', 'gitomatic_wp_branches');
		params.set('nonce', gitomatic_wp.branch_nonce);
		params.set('branch_page', page);
		load.disabled = true;
		status.textContent = gitomatic_wp.loading_branches;
		request = j.post(gitomatic_wp.ajax_url, params.toString()).done(response => {
			if (!response.success) {
				status.textContent = response.data || gitomatic_wp.manual_branch;
				load.textContent = gitomatic_wp.retry;
				return;
			}
			const data = response.data;
			if (page === 1) {
				choices.add(new Option(gitomatic_wp.select_branch, ''));
				if (branch.value) choices.add(new Option(branch.value, branch.value));
			}
			const manualOption = choices.querySelector('[data-manual]');
			if (manualOption) manualOption.remove();
			data.branches.forEach(name => {
				if (Array.from(choices.options).some(option => option.value === name)) return;
				choices.add(new Option(name, name));
			});
			if (!branch.value && !fields.source_id) branch.value = data.default;
			if (branch.value && !Array.from(choices.options).some(option => option.value === branch.value)) {
				choices.add(new Option(branch.value, branch.value));
			}
			const manual = new Option(gitomatic_wp.enter_branch, '');
			manual.dataset.manual = 'true';
			choices.add(manual);
			choices.value = branch.value;
			choices.hidden = false;
			const inputLabel = form.querySelector('label[for="repository_branch"]');
			if (inputLabel) inputLabel.htmlFor = 'gitomatic-wp-branch-select';
			choices.required = true;
			branch.hidden = true;
			branch.required = false;
			load.hidden = !data.more;
			load.textContent = gitomatic_wp.more_branches;
			page++;
			status.textContent = gitomatic_wp.manual_branch;
			preview();
		}).fail((xhr, reason) => {
			if (reason !== 'abort') {
				status.textContent = gitomatic_wp.branch_error;
				load.textContent = gitomatic_wp.retry;
			}
		}).always(() => { load.disabled = false; });
	}

	if (repo) repo.addEventListener('change', () => {
		let host = '';
		try { host = new URL(repo.value).hostname.toLowerCase(); } catch (error) { /* Partial URL. */ }
		const known = {'github.com': 'github', 'gitlab.com': 'gitlab', 'bitbucket.org': 'bitbucket', 'gitea.com': 'gitea'};
		if (known[host]) provider.value = known[host];
		preview();
	});
	form.addEventListener('change', event => {
		if (['repository_url', 'provider_type', 'is_private_repository', 'username', 'password', 'access_token'].includes(event.target.name)) {
			resetBranches();
			if (['repository_url', 'provider_type'].includes(event.target.name)) {
				branch.value = '';
				for (const key of ['access_token', 'username', 'password']) fields[key].value = '';
			}
			refreshCredentials();
			if (!fields.is_private_repository || !fields.is_private_repository.checked ||
				event.target.name === 'access_token' || event.target.name === 'password') loadBranches();
		}
		preview();
	});
	if (folder) folder.addEventListener('input', () => { automaticFolder = !folder.value; preview(); });
	branch.addEventListener('input', () => { choices.value = branch.value; preview(); });
	choices.addEventListener('change', () => {
		if (choices.selectedOptions[0].dataset.manual) {
			resetBranches();
			branch.focus();
		} else {
			branch.value = choices.value;
		}
		preview();
	});
	load.addEventListener('click', loadBranches);
	refreshCredentials();
	preview();
	if (fields.source_id) loadBranches();
}

function init_dashboard_listenes() {
	j('.gitomatic_wp_package_boxes button[data-copy-url-btn]').on('click', function() {
		const url = j(this).data('copy-url-btn');
		let $text_el = j(this).find('.text');
		let $status_el = j(this).closest('.gitomatic_wp_package_card').find('.gitomatic_wp_deploy_status');

		copy_dashboard_text(url).done(function() {
			$text_el.text( gitomatic_wp.copied_url_label );
			$status_el.removeClass('is-error').text( gitomatic_wp.copied_url_label );
			setTimeout(function() {
				$text_el.text( gitomatic_wp.copy_url_label );
				$status_el.text('');
			}, 2000);
		}).fail(function() {
			$status_el.addClass('is-error').text( gitomatic_wp.copy_failed_label );
		});
	});

	j('.gitomatic_wp_package_boxes button[data-show-ptd-btn]').on('click', function() {
		let $button = j(this);
		let is_expanded = $button.attr('aria-expanded') === 'true';
		let $package_box_action = j('#' + $button.attr('aria-controls'));

		$button.attr('aria-expanded', ! is_expanded);
		$button.find('.text').text( is_expanded ? gitomatic_wp.show_webhook_label : gitomatic_wp.hide_webhook_label );
		$package_box_action.prop('hidden', is_expanded);
	});

	j('.gitomatic_wp_package_boxes button[data-unlink-btn]').on('click', function(e) {
		e.preventDefault();

		if ( ! confirm( gitomatic_wp.unlink_confirm_label ) ) {
			return;
		}

		var $btn = j(this);
		var slug = $btn.data('unlink-slug');
		var type = $btn.data('unlink-type');
		var $package_box = $btn.closest('.gitomatic_wp_package_box');
		var $status_el = $package_box.find('.gitomatic_wp_deploy_status');

		$btn.attr('disabled', true);
		$status_el.removeClass('is-error is-success').text( gitomatic_wp.unlinking_label );

		j.ajax({
			url: gitomatic_wp.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'gitomatic_wp_unlink_package',
				nonce: gitomatic_wp.unlink_nonce,
				slug: slug,
				type: type
			}
		})
		.done(function(response) {
			if (response.success) {
				$package_box.fadeOut(300, function() {
					window.location.reload();
				});
			} else {
				$status_el.addClass('is-error').text( response.data.message || gitomatic_wp.error_label );
				$btn.removeAttr('disabled');
			}
		})
		.fail(function() {
			$status_el.addClass('is-error').text( gitomatic_wp.error_label );
			$btn.removeAttr('disabled');
		});
	});

	j('.gitomatic_wp_package_boxes button[data-trigger-ptd-btn]').on('click', function(e) {
		e.preventDefault();
		const endpoint_url = j(this).data('trigger-ptd-btn');
		let $parent_el = j(this);
		let $parent_text_el = j(this).find('.text');
		let $status_el = j(this).closest('.gitomatic_wp_package_card').find('.gitomatic_wp_deploy_status');
		const default_label = $parent_text_el.text();

		j.ajax({
			url: endpoint_url,
			type: 'POST',
			dataType: 'json',
			beforeSend: function() {
				$parent_el.addClass( 'loading' );
				$parent_el.attr( 'disabled', true );
				$parent_text_el.text( gitomatic_wp.deploying_now_label );
				$status_el.removeClass('is-error is-success').text( gitomatic_wp.deploying_now_label );
			}
		})
		.done(function(response) {
			if (response.last_deploy) {
				$parent_el.closest('.gitomatic_wp_package_box').find('.gitomatic-wp-last-deploy').prop('hidden', false).find('dd').text(response.last_deploy);
			}
			if (response.success) {
				const box = $parent_el.closest('.gitomatic_wp_package_box');
				j('.gitomatic_wp_package_box').filter(function() {
					return this.dataset.folder === box[0].dataset.folder;
				}).find('.gitomatic_wp_badge_success').prop('hidden', true);
				box.find('.gitomatic_wp_badge_success').prop('hidden', false);
				$parent_text_el.text( gitomatic_wp.deploy_completed_label );
				$status_el.addClass('is-success').text( response.message || gitomatic_wp.deploy_completed_label );

				setTimeout(function() {
					$parent_text_el.text( default_label );
				}, 2000);
			} else {
				$parent_text_el.text( default_label );
				$status_el.addClass('is-error').text( response.message || gitomatic_wp.error_label );
			}
		})
		.fail(function(response) {
			let message = response.responseJSON && response.responseJSON.message ? response.responseJSON.message : gitomatic_wp.error_label;
			$parent_text_el.text( default_label );
			$status_el.addClass('is-error').text( message );
		})
		.always(function() {
			$parent_el.removeClass( 'loading' ).removeAttr( 'disabled' );
		});
	});
}

function copy_dashboard_text(text) {
	let deferred = j.Deferred();

	if (navigator.clipboard && window.isSecureContext) {
		navigator.clipboard.writeText(text).then(deferred.resolve, deferred.reject);
		return deferred.promise();
	}

	let $textarea = j('<textarea readonly></textarea>');
	$textarea.css({
		position: 'fixed',
		opacity: 0,
		pointerEvents: 'none'
	});
	$textarea.val(text).appendTo('body').trigger('select');

	try {
		if (document.execCommand('copy')) {
			deferred.resolve();
		} else {
			deferred.reject();
		}
	} catch (error) {
		deferred.reject(error);
	}

	$textarea.remove();
	return deferred.promise();
}

function init_log_textarea_scroll() {
	var $textarea = j('.gitomatic_wp_log_textarea');
	if ($textarea.length) {
		$textarea.each(function() {
			this.scrollTop = this.scrollHeight;
		});
	}
}

function init_log_copy_button() {
	j('.gitomatic_wp_copy_logs').on('click', function() {
		var $button = j(this);
		var text = j('.gitomatic_wp_copy_log_source').val();

		copy_dashboard_text(text).then(function() {
			var originalText = $button.text();
			$button.text(gitomatic_wp.copied_url_label);
			window.setTimeout(function() {
				$button.text(originalText);
			}, 1500);
		});
	});
}
})(jQuery);
