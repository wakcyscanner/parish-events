/**
 * Carousel behavior: arrow scrolling, arrow visibility, and per-card
 * read-more toggles. The markup is server-rendered by PP_Render; this only
 * wires it up. Ported from the legacy Come to Me widget.
 */
(function () {
	'use strict';

	function cardScrollAmount(viewport) {
		var card = viewport.querySelector('.pp-card');
		if (!card) {
			return 300;
		}
		var track = viewport.querySelector('.pp-track');
		var gap = track ? parseFloat(getComputedStyle(track).gap) || 16 : 16;
		return card.offsetWidth + gap;
	}

	function updateArrows(viewport, prevBtn, nextBtn) {
		var maxScroll = viewport.scrollWidth - viewport.clientWidth;
		prevBtn.classList.toggle('pp-hidden', viewport.scrollLeft <= 1);
		nextBtn.classList.toggle('pp-hidden', viewport.scrollLeft >= maxScroll - 1);
	}

	function setupReadMore(card) {
		var wrap = card.querySelector('.pp-card-desc-wrap');
		var toggle = card.querySelector('.pp-card-toggle');
		if (!wrap || !toggle) {
			return;
		}
		var desc = wrap.querySelector('.pp-card-desc');

		if (desc.scrollHeight > wrap.offsetHeight + 2) {
			wrap.classList.add('pp-overflowing');
			toggle.classList.add('pp-visible');
		}

		toggle.addEventListener('click', function () {
			var expanded = card.classList.toggle('pp-expanded');
			if (expanded) {
				toggle.textContent = toggle.getAttribute('data-less') || 'Show less';
				wrap.classList.remove('pp-overflowing');
			} else {
				toggle.textContent = toggle.getAttribute('data-more') || 'Read more';
				if (desc.scrollHeight > wrap.offsetHeight + 2) {
					wrap.classList.add('pp-overflowing');
				}
			}
		});

		// Stash the initial label so expand/collapse round-trips any translation.
		toggle.setAttribute('data-more', toggle.textContent);
	}

	function initCarousel(carousel) {
		if (carousel.getAttribute('data-pp-init')) {
			return;
		}
		carousel.setAttribute('data-pp-init', '1');

		var viewport = carousel.querySelector('.pp-viewport');
		var prevBtn = carousel.querySelector('.pp-arrow-prev');
		var nextBtn = carousel.querySelector('.pp-arrow-next');
		if (!viewport || !prevBtn || !nextBtn) {
			return;
		}

		prevBtn.addEventListener('click', function () {
			viewport.scrollBy({ left: -cardScrollAmount(viewport), behavior: 'smooth' });
		});
		nextBtn.addEventListener('click', function () {
			viewport.scrollBy({ left: cardScrollAmount(viewport), behavior: 'smooth' });
		});
		viewport.addEventListener('scroll', function () {
			updateArrows(viewport, prevBtn, nextBtn);
		});
		updateArrows(viewport, prevBtn, nextBtn);

		var cards = carousel.querySelectorAll('.pp-card');
		for (var i = 0; i < cards.length; i++) {
			setupReadMore(cards[i]);
		}
	}

	function init() {
		var carousels = document.querySelectorAll('.pp-carousel');
		for (var i = 0; i < carousels.length; i++) {
			initCarousel(carousels[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
