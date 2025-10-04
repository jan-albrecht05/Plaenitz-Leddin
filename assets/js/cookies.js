function acceptCookies(){
    document.getElementById('cookie-banner').style.transform = 'translateX(110%)';
    localStorage.setItem('cookiesAccepted', 'true');
}
function checkCookies(){
    if(localStorage.getItem('cookiesAccepted') === 'true'){
        document.getElementById('cookie-banner').style.display = 'none';
    } else {
        document.getElementById('cookie-banner').style.display = 'flex';
    }
}
window.onload = checkCookies;