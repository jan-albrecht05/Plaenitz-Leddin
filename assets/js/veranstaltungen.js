window.onload = function() {
    //get default view from localStorage
    const defaultView = localStorage.getItem('veranstaltungenView') || 'list';
    if (defaultView === 'list') {
        toggleListView();
    } else {
        toggleGridView();
    }
}
function toggleListView() {
    document.getElementById('list').classList.add('active');
    document.getElementById('grid').classList.remove('active');
    const cssLink = document.querySelector('#css link');
    cssLink.href = '../assets/css/list-view.css';
    //save to localStorage
    localStorage.setItem('veranstaltungenView', 'list');    
}
function toggleGridView() {
    document.getElementById('grid').classList.add('active');
    document.getElementById('list').classList.remove('active');
    const cssLink = document.querySelector('#css link');
    cssLink.href = '../assets/css/grid-view.css';
    //save to localStorage
    localStorage.setItem('veranstaltungenView', 'grid');
}