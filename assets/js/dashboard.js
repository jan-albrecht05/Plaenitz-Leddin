// wait for click on class member to toggle class open
document.querySelectorAll('.member').forEach(member => {
    member.addEventListener('click', () => {
        member.classList.toggle('open');
    });
});