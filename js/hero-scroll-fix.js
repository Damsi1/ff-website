document.addEventListener(
    "click",
    event => {
        const trigger = event.target.closest('a[href^="#"]');
        if (!trigger) {
            return;
        }

        const href = trigger.getAttribute("href");
        if (!href || href.length < 2) {
            return;
        }

        const targetId = href.slice(1);
        if (!targetId || trigger.id !== targetId) {
            return;
        }

        const hero = trigger.closest(".hero-modern");
        if (!hero) {
            return;
        }

        let nextSection = hero.nextElementSibling;
        while (nextSection && nextSection.tagName !== "SECTION") {
            nextSection = nextSection.nextElementSibling;
        }

        if (!nextSection) {
            return;
        }

        const navbar = document.querySelector(".navbar");
        const navbarHeight = navbar ? navbar.getBoundingClientRect().height : 0;
        const sectionTop = nextSection.getBoundingClientRect().top + window.scrollY;
        const targetTop = Math.max(0, sectionTop - navbarHeight);

        event.preventDefault();
        event.stopImmediatePropagation();

        window.scrollTo({
            top: targetTop,
            behavior: "smooth"
        });
    },
    true
);
