function dreibalkensymbol() {
    document.getElementById("right").classList.toggle("responsive");
}
function showNotifications(){
    if(document.getElementById("right").classList.contains("responsive")){
        document.getElementById("right").classList.remove("responsive");
    };
    document.getElementById("notifications-popup").classList.toggle("hidden");
}
function hideNotifications(){
    document.getElementById("notifications-popup").classList.toggle("hidden");
}