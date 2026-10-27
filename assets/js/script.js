window.addEventListener("scroll",()=>{


let cards=document.querySelectorAll(".card-box");


cards.forEach(card=>{


let position=card.getBoundingClientRect().top;


if(position < window.innerHeight-100){

card.style.opacity="1";

card.style.transform="translateY(0)";

}


});


});