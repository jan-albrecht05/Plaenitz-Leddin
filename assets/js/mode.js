// toggle dark mode
const toggleCheckbox = document.getElementById("toggle-checkbox");
toggleCheckbox.addEventListener("change", () => {
    if (toggleCheckbox.checked) {
        document.documentElement.setAttribute("data-theme", "dark");
    } else {
        document.documentElement.setAttribute("data-theme", "light");
    }
});
// set initial mode based on user preference
if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
    toggleCheckbox.checked = true;
}
document.documentElement.setAttribute("data-theme", toggleCheckbox.checked ? "dark" : "light");

// save mode preference to local storage
toggleCheckbox.addEventListener("change", () => {
    localStorage.setItem("theme", toggleCheckbox.checked ? "dark" : "light");
});
// load mode preference from local storage
const savedTheme = localStorage.getItem("theme");
if (savedTheme) {
    document.documentElement.setAttribute("data-theme", savedTheme);
    toggleCheckbox.checked = savedTheme === "dark";
}