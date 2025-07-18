<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Gray Bag of Tricks Tracker</title>
  <style>
    body { font-family: sans-serif; max-width:800px; margin:20px auto; }
    h1 { text-align:center; color:#444; }
    .btn-small { padding:2px 6px; font-size:0.85em; margin:1px; cursor:pointer; }
    .animal { border:1px solid #ccc; padding:10px; margin:10px 0; transition:background .3s; }
    .animal.dead { background:#eee; color:#999; }
    .controls input { width:50px; text-align:center; }
    .attack-result.advantage { color:blue; font-weight:bold; }
    .attack-result.disadvantage { color:orange; font-weight:bold; }
    .attack-result.critical { color:red; font-weight:bold; }
    .attack-result.fail { color:yellow; font-weight:bold; }
    .damage-result.critical { color:red; font-weight:bold; }
    .roll-output { font-family:monospace; white-space:pre-wrap; margin-top:8px; }
    .notes { font-size:0.9em; margin-bottom:8px; }
  </style>
</head>
<body>
  <h1>Gray Bag of Tricks – Summon 3</h1>
  <button class="btn-small" onclick="summonAnimals()">Summon Animals</button>
  <div id="creatures"></div>

  <script>
    const grayBagTable = {
      1:"Giant Weasel",2:"Giant Rat",3:"Badger",4:"Boar",
      5:"Panther",6:"Giant Badger",7:"Dire Wolf",8:"Giant Elk"
    };
    let animals = {};

    fetch('animals.json')
      .then(r => r.json())
      .then(j => animals = j)
      .catch(e => { console.error(e); alert('Failed to load animals.json'); });

    function rollD8(){ return Math.floor(Math.random()*8)+1; }
    function rollD20(advType=0){
      const r1 = Math.ceil(Math.random()*20);
      const r2 = advType!==0 ? Math.ceil(Math.random()*20) : null;
      const fin = advType===1 ? Math.max(r1,r2) : advType===-1 ? Math.min(r1,r2) : r1;
      return { rolls: [r1, r2].filter(x=>x!==null), value: fin };
    }
    function rollDamage(dmgStr){
      const m = dmgStr.match(/(\d+)d(\d+)([+-]\d+)?/);
      if(m){
        const cnt=+m[1], die=+m[2], mod=+m[3]||0;
        let rolls=[], total=0;
        for(let i=0;i<cnt;i++){ const r=Math.ceil(Math.random()*die); rolls.push(r); total+=r; }
        total += mod;
        return { total, breakdown: `${rolls.join(' + ')} ${mod>=0?'+':'-'}${Math.abs(mod)} = ${total}` };
      } else {
        const val = parseInt(dmgStr);
        return { total: val, breakdown: `${val}` };
      }
    }

    function toggleAdvDis(n, a, type) {
      const advEl = document.getElementById(`adv-${n}-${a}`);
      const disEl = document.getElementById(`dis-${n}-${a}`);
      if(type === 'adv' && advEl.checked) disEl.checked = false;
      if(type === 'dis' && disEl.checked) advEl.checked = false;
    }

    function adjustHP(n, delta){
      const inp = document.getElementById(`hp-${n}`);
      inp.value = parseInt(inp.value) + delta;
      checkDead(n);
    }
    function checkDead(n){
      const hp = parseInt(document.getElementById(`hp-${n}`).value);
      document.getElementById(`animal-${n}`).classList.toggle('dead', hp<=0);
    }
    function multiAttack(n){
      const nm = document.getElementById(`animal-${n}`).dataset.name;
      const b = animals[nm];
      if(!b.multiattackIndices) return;
      b.multiattackIndices.forEach(idx => {
        doAttack(n, idx, b.actions[idx].bonus);
      });
    }

    function doAttack(n, a, bonus){
      const advChecked = document.getElementById(`adv-${n}-${a}`).checked;
      const disChecked = document.getElementById(`dis-${n}-${a}`).checked;
      let advType = 0;
      if(advChecked && !disChecked) advType = 1;
      else if(disChecked && !advChecked) advType = -1;
      const roll = rollD20(advType);
      const final = roll.value;
      const sum = final + bonus;
      const isCrit = final === 20;
      const isFail = final === 1 && advType === 0;
      let cls = '';
      if(isCrit) cls = 'critical';
      else if(isFail) cls = 'fail';
      else if(advType === 1) cls = 'advantage';
      else if(advType === -1) cls = 'disadvantage';
      const out = document.getElementById(`atk-roll-${n}-${a}`);
      out.className = `attack-result ${cls}`;
      out.innerHTML = `Rolls: ${roll.rolls.join(', ')} &rarr; ${final}+${bonus} = <strong>${sum}</strong>`;
    }

    function doDamage(n, a, dmgStr){
      const atkSpan = document.getElementById(`atk-roll-${n}-${a}`);
      const isCrit = atkSpan.classList.contains('critical');
      const res = rollDamage(dmgStr);
      let breakdown = res.breakdown;
      let total = res.total;
      if(isCrit){
        total = res.total * 2;
        breakdown += ` (critical x2 = ${total})`;
      }
      const out = document.getElementById(`dmg-roll-${n}-${a}`);
      out.className = isCrit ? 'damage-result critical' : 'damage-result';
      const parts = breakdown.split('=');
      if(parts.length === 2) {
        out.innerHTML = `Damage: ${parts[0].trim()} = <strong>${parts[1].trim()}</strong>`;
      } else {
        out.innerHTML = `Damage: <strong>${breakdown}</strong>`;
      }
    }

    function summonAnimals(){
      const c = document.getElementById('creatures');
      c.innerHTML = '';
      for(let i=1;i<=3;i++){
        const nm = grayBagTable[rollD8()];
        const b = animals[nm];
        if(!b) continue;
        const div = document.createElement('div');
        div.className='animal'; div.id=`animal-${i}`; div.dataset.name=nm;

        let html = `<h2>${b.name}</h2>
          <p><strong>CR:</strong> ${b.cr} | <strong>Prof:</strong> ${b.proficiency}</p>
          <p><strong>AC:</strong> ${b.ac} | <strong>HP:</strong>
            <input id="hp-${i}" type="number" value="${b.hp}" onchange="checkDead(${i})">
            <button class="btn-small" onclick="adjustHP(${i},1)">+1</button>
            <button class="btn-small" onclick="adjustHP(${i},-1)">-1</button>
            | <strong>Speed:</strong> ${b.speed}
          </p>
          <p><strong>STR</strong> ${b.str} | <strong>DEX</strong> ${b.dex} | <strong>CON</strong> ${b.con}
            | <strong>INT</strong> ${b.int} | <strong>WIS</strong> ${b.wis} | <strong>CHA</strong> ${b.cha}
          </p>
          <p><strong>Skills:</strong> ${b.skills||'–'} | <strong>Senses:</strong> ${b.senses||'–'} | <strong>Lang:</strong> ${b.languages||'–'}</p>`;

        if(b.traits){
          html += `<div class="notes"><strong>Traits:</strong><ul>`;
          b.traits.forEach(t=> html+=`<li>${t}</li>`);
          html += `</ul></div>`;
        }
        if(b.multiattackIndices){
          html += `<button class="btn-small" onclick="multiAttack(${i})">Multiattack</button>`;
        }
        html += `<ul>`;
        b.actions.forEach((a,idx)=>{
          html += `<li>
              <em>${a.name}</em>. +${a.bonus} to hit, reach ${a.reach}. Hit: ${a.damage} ${a.damageType}.${a.extra? ' '+a.extra : ''}
              <br>
              <label><input type="checkbox" id="adv-${i}-${idx}" class="btn-small"
                onchange="toggleAdvDis(${i},${idx},'adv')">Adv</label>
              <label><input type="checkbox" id="dis-${i}-${idx}" class="btn-small"
                onchange="toggleAdvDis(${i},${idx},'dis')">Dis</label>
              <button class="btn-small" onclick="doAttack(${i},${idx},${a.bonus})">Atk</button>
              <span id="atk-roll-${i}-${idx}" class="attack-result"></span>
              <button class="btn-small" onclick="doDamage(${i},${idx},'${a.damage}')">Dmg</button>
              <span id="dmg-roll-${i}-${idx}" class="damage-result"></span>
            </li>`;
        });
        html += `</ul><div class="roll-output" id="output-${i}"></div>`;
        div.innerHTML = html;
        c.appendChild(div);
      }
    }
  </script>
</body>
</html>
