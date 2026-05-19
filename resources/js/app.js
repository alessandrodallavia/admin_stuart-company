import './bootstrap';

// Swiper
import Swiper from 'swiper/bundle';
import 'swiper/css/bundle';
import { Autoplay } from 'swiper/modules';

document.addEventListener('DOMContentLoaded', () => {
    // Primo swiper
    document.querySelectorAll('.fmSwiper').forEach(swiperEl => {
        const section = swiperEl.closest('section')
        const prev = section.querySelector('.fm-prev')
        const next = section.querySelector('.fm-next')
        const progressFill = section.querySelector('.fm-progress-fill')

        const uniqueSlides = 3
        const startIndex = 1

        const swiper = new Swiper(swiperEl, {
            slidesPerView: 'auto',
            centeredSlides: true,
            spaceBetween: 24,
            speed: 550,
            grabCursor: true,

            loop: false,
            // IMPORTANTI: con poche slide aiutano la stabilità del loop
            loopedSlides: uniqueSlides,
            loopAdditionalSlides: uniqueSlides * 3,

            rewind: true,
            slideToClickedSlide: true,

            navigation: { prevEl: prev, nextEl: next },

            breakpoints: {
            768: { spaceBetween: 28 },
            1024:{ spaceBetween: 32 },
            },

            on: {
            init() {
                this.slideToLoop(startIndex, 0)
                updateProgress(this)
            },
            slideChange() {
                updateProgress(this)
            },
            click(sw) {
                const clicked = sw.clickedSlide
                if (!clicked) return

                const realIndexAttr = clicked.getAttribute('data-swiper-slide-index')
                if (realIndexAttr == null) return
                const realIndex = parseInt(realIndexAttr, 10)
                if (Number.isNaN(realIndex)) return

                // ✅ trova TUTTE le copie di quella slide (cloni inclusi)
                const candidates = []
                sw.slides.forEach((slide, i) => {
                if (parseInt(slide.getAttribute('data-swiper-slide-index'), 10) === realIndex) {
                    candidates.push(i)
                }
                })

                if (!candidates.length) return

                // ✅ scegli la copia più vicina all'indice attuale
                const from = sw.activeIndex
                let best = candidates[0]
                let bestDist = Infinity
                candidates.forEach(i => {
                const dist = Math.abs(i - from)
                if (dist < bestDist) {
                    bestDist = dist
                    best = i
                }
                })

                sw.slideTo(best)
            },
            },
        })

        function updateProgress(sw) {
            const real = (sw.realIndex % uniqueSlides + uniqueSlides) % uniqueSlides
            const pct = ((real + 1) / uniqueSlides) * 100
            progressFill.style.width = `${pct}%`
        }
    })

    document.querySelectorAll('.mySwiper').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
        slidesPerView: 1.2,
        spaceBetween: 16,
        navigation: {
            nextEl: scope.querySelector('.swiper-button-next'),
            prevEl: scope.querySelector('.swiper-button-prev'),
        },
        breakpoints: {
            768: { slidesPerView: 2.2, spaceBetween: 24 },
            1024: { slidesPerView: 3, spaceBetween: 12 },
        },
        watchOverflow: true,
        })
    })

    document.querySelectorAll('.mySwiper2').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
        slidesPerView: 1.2,
        spaceBetween: 16,
        navigation: {
            nextEl: scope.querySelector('.swiper-button-next2'),
            prevEl: scope.querySelector('.swiper-button-prev2'),
        },
        breakpoints: {
            768: { slidesPerView: 2.2, spaceBetween: 24 },
            1024: { slidesPerView: 3, spaceBetween: 12 },
        },
        watchOverflow: true,
        })
    })

    document.querySelectorAll('.mySwiper3').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
            modules: [Autoplay],
            slidesPerView: 1,
            spaceBetween: 16,
            navigation: {
                nextEl: scope.querySelector('.swiper-button-next3'),
                prevEl: scope.querySelector('.swiper-button-prev3'),
            },
            autoplay: {
                delay: 4000, // 4 secondi
                disableOnInteraction: false,
            },
            loop: true,
            breakpoints: {
                768: { slidesPerView: 1, spaceBetween: 24 },
                1024: { slidesPerView: 1, spaceBetween: 12 },
            },
            watchOverflow: true,
        })
    })

    document.querySelectorAll('.mySwiper4').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
            modules: [Autoplay],
            slidesPerView: 1,
            spaceBetween: 16,
            navigation: {
                nextEl: scope.querySelector('.swiper-button-next4'),
                prevEl: scope.querySelector('.swiper-button-prev4'),
            },
            autoplay: {
                delay: 3000, // 3 secondi
                disableOnInteraction: false,
            },
            loop: true,
            breakpoints: {
                768: { slidesPerView: 1, spaceBetween: 24 },
                1024: { slidesPerView: 4, spaceBetween: 12 },
                1280: { slidesPerView: 4, spaceBetween: 12 },
                1763: { slidesPerView: 4, spaceBetween: 12 },
            },
            watchOverflow: true,
        })
    })

    document.querySelectorAll('.mySwiper5').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
            modules: [Autoplay],
            slidesPerView: 2.3,
            spaceBetween: 16,
            navigation: {
                nextEl: scope.querySelector('.swiper-button-next5'),
                prevEl: scope.querySelector('.swiper-button-prev5'),
            },
            autoplay: {
                delay: 0,
                disableOnInteraction: false,
            },
            breakpoints: {
                768: { slidesPerView: 4, spaceBetween: 24 },
                1024: { slidesPerView: 6, spaceBetween: 24 },
                1280: { slidesPerView: 8, spaceBetween: 12 },
                1441: { slidesPerView: 8, spaceBetween: 12 },
                1920: { slidesPerView: 8, spaceBetween: 12 },
            },
            speed: 4000,
            loop: true,
            freeMode: true,
            watchOverflow: true,
        })
    })

    document.querySelectorAll('.mySwiper6').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
            modules: [Autoplay],
            slidesPerView: 3.4,
            spaceBetween: 16,
            navigation: {
                nextEl: scope.querySelector('.swiper-button-next6'),
                prevEl: scope.querySelector('.swiper-button-prev6'),
            },
            autoplay: {
                delay: 0,
                disableOnInteraction: false,
            },
            breakpoints: {
                768: { slidesPerView: 4, spaceBetween: 24 },
                1024: { slidesPerView: 6, spaceBetween: 24 },
                1280: { slidesPerView: 8, spaceBetween: 12 },
                1441: { slidesPerView: 8, spaceBetween: 12 },
                1920: { slidesPerView: 10, spaceBetween: 12 },
            },
            speed: 4000,
            loop: true,
            freeMode: true,
            watchOverflow: true,
        })
    })

    document.querySelectorAll('.mySwiper7').forEach(swiperEl => {
        const scope = swiperEl.closest('section')
        new Swiper(swiperEl, {
            modules: [Autoplay],
            slidesPerView: 1,
            spaceBetween: 16,
            navigation: {
                nextEl: scope.querySelector('.swiper-button-next7'),
                prevEl: scope.querySelector('.swiper-button-prev7'),
            },
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            breakpoints: {
                768: { slidesPerView: 1, spaceBetween: 24 },
                1024: { slidesPerView: 2, spaceBetween: 24 },
                1280: { slidesPerView: 2, spaceBetween: 12 },
                1441: { slidesPerView: 4, spaceBetween: 12 },
                1920: { slidesPerView: 4, spaceBetween: 24 },
            },
            loop: true,
            watchOverflow: true,
        })
    })
})