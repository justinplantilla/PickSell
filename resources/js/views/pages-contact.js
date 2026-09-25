document.addEventListener('click', (event) => {
	const helpCard = event.target.closest('[data-help-type]');
	if (!helpCard) return;

	const issueSelect = document.getElementById('issueTypeSelect');
	if (!issueSelect) return;

	const selectedType = helpCard.dataset.helpType;
	document.querySelectorAll('[data-help-type]').forEach((card) => {
		card.classList.remove('is-selected');
		card.setAttribute('aria-pressed', 'false');
	});
	for (const option of issueSelect.options) {
		if (option.value.trim().toLowerCase() === selectedType.trim().toLowerCase() || option.textContent.trim().toLowerCase() === selectedType.trim().toLowerCase()) {
			option.selected = true;
			helpCard.classList.add('is-selected');
			helpCard.setAttribute('aria-pressed', 'true');
			break;
		}
	}

	document.getElementById('form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

