document.addEventListener("DOMContentLoaded", () => {
    if (typeof bootstrap === "undefined") {
        return;
    }

    const getLightboxImageSrc = img => img?.dataset?.src || img?.currentSrc || img?.src || "";

    const existingModal = document.getElementById("fdGlobalLightbox");
    if (!existingModal) {
        document.body.insertAdjacentHTML(
            "beforeend",
            `
            <div class="modal fade fd-global-lightbox" id="fdGlobalLightbox" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header justify-content-end">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div id="fdGlobalLightboxCarousel" class="carousel slide fd-global-lightbox-carousel" data-bs-touch="true">
                                <div class="carousel-inner"></div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#fdGlobalLightboxCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#fdGlobalLightboxCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `
        );
    }

    const lightboxEl = document.getElementById("fdGlobalLightbox");
    const lightboxInner = document.querySelector("#fdGlobalLightboxCarousel .carousel-inner");
    const lightboxCarouselEl = document.getElementById("fdGlobalLightboxCarousel");
    const lightboxModal = lightboxEl ? new bootstrap.Modal(lightboxEl) : null;
    let lightboxCarousel = null;

    const openLightbox = (items, activeIndex) => {
        if (!lightboxInner || !lightboxModal || !lightboxCarouselEl || !items.length) {
            return;
        }

        lightboxInner.innerHTML = items
            .map((item, index) => {
                const activeClass = index === activeIndex ? " active" : "";
                const alt = item.alt ? item.alt.replace(/"/g, "&quot;") : "Bildansicht";
                return `
                    <div class="carousel-item${activeClass}">
                        <img src="${item.src}" alt="${alt}">
                    </div>
                `;
            })
            .join("");

        lightboxCarousel?.dispose();
        lightboxCarousel = new bootstrap.Carousel(lightboxCarouselEl, {
            interval: false,
            ride: false,
            touch: true
        });

        lightboxModal.show();
    };

    const isIgnoredImage = img =>
        img.classList.contains("logo-img")
        || img.closest(".navbar")
        || img.closest(".footer")
        || img.closest(".fd-global-lightbox")
        || img.closest(".fd-lightbox")
        || img.closest(".fd-timeline-img .carousel");

    document.querySelectorAll("img").forEach(img => {
        if (isIgnoredImage(img)) {
            return;
        }

        img.classList.add("fd-lightbox-target");

        if (img.closest(".carousel")) {
            return;
        }

        img.addEventListener("click", event => {
            event.preventDefault();
            event.stopPropagation();
            openLightbox(
                [{ src: getLightboxImageSrc(img), alt: img.alt || "Bildansicht" }],
                0,
                img.alt || "Bildansicht"
            );
        });
    });

    document.querySelectorAll(".carousel").forEach(carousel => {
        if (carousel.id === "timelineLightboxCarousel" || carousel.id === "fdGlobalLightboxCarousel") {
            return;
        }

        if (carousel.closest(".fd-lightbox")) {
            return;
        }

        if (carousel.closest(".fd-timeline-img")) {
            return;
        }

        const images = Array.from(carousel.querySelectorAll(".carousel-item img")).filter(img => !isIgnoredImage(img));

        if (!images.length) {
            return;
        }

        images.forEach(img => img.classList.add("fd-lightbox-target"));

        const titleSource =
            carousel.getAttribute("aria-label")
            || carousel.dataset.lightboxTitle
            || carousel.closest("section")?.querySelector("h2, h3, h4")?.textContent?.trim()
            || "Galerie";

        const triggerArea = carousel;

        triggerArea.addEventListener("click", event => {
            if (event.target.closest(".carousel-control-prev, .carousel-control-next, .carousel-indicators")) {
                return;
            }

            const activeImage = carousel.querySelector(".carousel-item.active img") || images[0];
            const activeIndex = Math.max(0, images.indexOf(activeImage));

            openLightbox(
                images.map(img => ({
                    src: getLightboxImageSrc(img),
                    alt: img.alt || titleSource
                })),
                activeIndex,
                undefined
            );
        });
    });
});
