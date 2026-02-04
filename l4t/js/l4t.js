const L4T = {

modal: document.getElementById("l4tModal"),
content: document.getElementById("modalContent"),
title: document.getElementById("modalTitle"),

open(title, html, onSave) {
    this.title.textContent = title;
    this.content.innerHTML = html;
    this.modal.classList.remove("hidden");

    document.getElementById("modalSave").onclick = () => {
        onSave();
        this.close();
    };
},

close() {
    this.modal.classList.add("hidden");
},

post(payload) {
    return fetch("/swad/controllers/l4t/l4t_update.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(payload)
    }).then(r => r.json());
},

// ===== ОПЫТ =====

editExp(current = []) {

    let rows = current.map(e => `
      <div class="exp-row">
        <input class="exp-role" value="${e.role}">
        <input class="exp-years" type="number" value="${e.years}">
        <span class="del">×</span>
      </div>
    `).join("");

    this.open("Опыт", `
      <div id="expWrap">${rows}</div>
      <button id="addExp">+</button>
    `, () => {

        const data = [...document.querySelectorAll(".exp-row")].map(r => ({
            role: r.querySelector(".exp-role").value
                .replace(/[<>\"']/g,'').slice(0,30),

            years: parseInt(r.querySelector(".exp-years").value)||0
        }));

        this.post({ type:"exp", data })
            .then(()=>location.reload());
    });

    document.getElementById("addExp").onclick = () => {
        document.getElementById("expWrap").insertAdjacentHTML("beforeend",`
            <div class="exp-row">
              <input class="exp-role">
              <input class="exp-years" type="number">
              <span class="del">×</span>
            </div>
        `);
    };
},

// ===== ФАЙЛЫ / ССЫЛКИ =====

editFiles(current = []) {

let rows = current.map(f => `
 <div class="file-row">
   <select class="ftype">
     <option ${f.type=="link"?"selected":""}>link</option>
     <option ${f.type=="file"?"selected":""}>file</option>
   </select>

   <input class="fval" value="${f.value}">
 </div>
`).join("");

this.open("Доп. данные", `
 <div id="fileWrap">${rows}</div>
 <button id="addFile">+</button>
`, () => {

 const data = [...document.querySelectorAll(".file-row")].map(r => ({
   type: r.querySelector(".ftype").value,
   value: r.querySelector(".fval").value
     .replace(/[<>\"']/g,'').slice(0,200)
 }));

 this.post({type:"files", data})
     .then(()=>location.reload());
});

document.getElementById("addFile").onclick = () => {
 document.getElementById("fileWrap").insertAdjacentHTML("beforeend",`
   <div class="file-row">
     <select class="ftype">
       <option>link</option>
       <option>file</option>
     </select>
     <input class="fval">
   </div>
 `);
};

},

// ===== ПРОЕКТЫ =====

editProjects(current=[]) {

let rows = current.map(p=>`
<div class="proj-row">
 <input class="plink" value="${p.url}">
 <div class="preview"
      style="background:url(${p.cover}) center/cover"></div>
</div>`).join("");

this.open("Проекты",`
 <div id="projWrap">${rows}</div>
 <button id="addProj">+</button>
`,()=>{

 const data=[...document.querySelectorAll(".proj-row")]
 .map(r=>({
   url:r.querySelector(".plink").value,
   cover:r.querySelector(".preview").dataset.cover||""
 }));

 this.post({type:"projects",data})
     .then(()=>location.reload());
});

document.getElementById("addProj").onclick=()=>{
 document.getElementById("projWrap")
 .insertAdjacentHTML("beforeend",`
  <div class="proj-row">
   <input class="plink">
   <div class="preview"></div>
  </div>`);
};

this.content.addEventListener("change",e=>{

 if(!e.target.classList.contains("plink"))return;

 const url=e.target.value;
 const m=url.match(/dustore\.ru\/g\/(\d+)/);

 if(m){
   fetch("/api/game_preview.php?id="+m[1])
   .then(r=>r.json())
   .then(g=>{
     const pr=e.target.parentNode.querySelector(".preview");
     pr.style.backgroundImage=`url(${g.cover_url})`;
     pr.dataset.cover=g.cover_url;
   });
 }

});

},

// ===== О СЕБЕ =====

editAbout(text=""){

this.open("О себе",`
 <textarea id="aboutText"
  maxlength="1000">${text}</textarea>
`,()=>{

 const v=document.getElementById("aboutText").value
   .replace(/[<>\"']/g,'')
   .slice(0,1000);

 this.post({type:"about",data:v})
     .then(()=>location.reload());
});

}

};
