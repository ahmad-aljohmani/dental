const menuToggle = document.querySelector('#menuToggle');
const navLinks = document.querySelector('#navLinks');
const themeToggle = document.querySelector('#themeToggle');
const odontogram = document.querySelector('#odontogram');

menuToggle?.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('open');
    menuToggle.setAttribute('aria-expanded', String(isOpen));
});

document.querySelectorAll('.nav-links a').forEach((link) => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        menuToggle?.setAttribute('aria-expanded', 'false');
    });
});

themeToggle?.addEventListener('click', () => {
    document.body.classList.toggle('dark');
    const isDark = document.body.classList.contains('dark');
    themeToggle.textContent = isDark ? '☀' : '☾';
    themeToggle.setAttribute('aria-pressed', String(isDark));
});

if (odontogram) {
    const toothTabs = odontogram.querySelectorAll('[data-tooth-type]');
    const toothCharts = odontogram.querySelectorAll('[data-tooth-chart]');
    const toothCount = odontogram.querySelector('#selectedTeethCount');
    const toothList = odontogram.querySelector('#selectedTeethList');

    const updateToothSelection = () => {
        const selected = [...odontogram.querySelectorAll('.tooth-checkbox:checked')]
            .map((tooth) => tooth.value)
            .sort((first, second) => Number(first) - Number(second));

        toothCount.textContent = String(selected.length);
        toothList.textContent = selected.length
            ? `الأسنان المحددة: ${selected.join('، ')}`
            : 'لم يتم اختيار أسنان';
    };

    toothTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const type = tab.dataset.toothType;
            toothTabs.forEach((item) => {
                const isActive = item === tab;
                item.classList.toggle('active', isActive);
                item.setAttribute('aria-pressed', String(isActive));
            });
            toothCharts.forEach((chart) => {
                chart.hidden = chart.dataset.toothChart !== type;
            });
        });
    });

    odontogram.querySelectorAll('.tooth-checkbox').forEach((tooth) => {
        tooth.addEventListener('change', updateToothSelection);
    });

    updateToothSelection();
}
