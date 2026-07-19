<?php
/**
 * chatbot.php
 * -----------------------------------------
 * A simple FAQ chatbot widget for parents/visitors.
 * No AI / no API calls — matches keywords against a
 * predefined list of Q&A pairs (edit $faqData below).
 *
 * HOW TO USE:
 * Add this line right before </body> on any PUBLIC
 * page (e.g. your homepage / index.php):
 *
 *     <?php include 'chatbot.php'; ?>
 *
 * Do NOT include this on the admin dashboard pages —
 * it's meant for parents/visitors on the public site.
 */

// ── EDIT YOUR FAQ CONTENT HERE ──────────────────────────
$faqData = [
    [
        'keywords' => ['fee', 'fees', 'cost', 'price', 'payment', 'how much'],
        'question' => 'What are the fees?',
        'answer'   => 'Our monthly fee is LKR 5,000. This covers tuition, activities, and basic supplies. Please contact the office for the latest fee structure and any sibling discounts.'
    ],
    [
        'keywords' => ['time', 'timing', 'hours', 'open', 'close', 'schedule'],
        'question' => 'What are the school timings?',
        'answer'   => 'We are open Monday to Friday, 8:00 AM to 1:00 PM. Extended day-care hours may be available on request — please ask at the office.'
    ],
    [
        'keywords' => ['admission', 'admissions', 'enroll', 'enrol', 'register', 'apply', 'join'],
        'question' => 'How do I apply for admission?',
        'answer'   => 'You can start the admission process by visiting our office with your child\'s birth certificate and a passport-size photo, or by calling us to schedule a visit.'
    ],
    [
        'keywords' => ['age', 'old', 'years'],
        'question' => 'What age group do you accept?',
        'answer'   => 'We welcome children aged 2 to 7 years, grouped by age into our Sunflower, Rainbow, Butterfly, and Star classes.'
    ],
    [
        'keywords' => ['class', 'classes', 'sunflower', 'rainbow', 'butterfly', 'star'],
        'question' => 'What classes do you have?',
        'answer'   => 'We currently have 4 classes: Sunflower, Rainbow, Butterfly, and Star — each led by an experienced teacher with a focus on age-appropriate learning.'
    ],
    [
        'keywords' => ['activity', 'activities', 'program', 'programme', 'curriculum', 'learn'],
        'question' => 'What activities do you offer?',
        'answer'   => 'Our program includes art & craft, music & movement, early literacy, numeracy games, outdoor play, and social skill-building activities.'
    ],
    [
        'keywords' => ['contact', 'phone', 'number', 'call', 'address', 'location', 'where'],
        'question' => 'How can I contact you?',
        'answer'   => 'You can reach us by phone or visit us in person — please check the Contact section on our homepage for our exact phone number and address.'
    ],
    [
        'keywords' => ['food', 'meal', 'lunch', 'snack', 'eat'],
        'question' => 'Do you provide meals?',
        'answer'   => 'We provide a healthy snack during the day. Parents are welcome to pack a lunch box — please let us know about any allergies in advance.'
    ],
    [
        'keywords' => ['holiday', 'holidays', 'vacation', 'closed'],
        'question' => 'What are your holidays?',
        'answer'   => 'We follow the standard school holiday calendar including public holidays. A detailed term calendar is available at the office.'
    ],
    [
        'keywords' => ['attendance', 'absent', 'sick', 'leave'],
        'question' => 'What if my child is absent?',
        'answer'   => 'Please inform the school by phone if your child will be absent, especially in case of illness, so we can keep accurate attendance records.'
    ],
];
$faqJson = json_encode($faqData);
?>
<style>
  :root {
    --cb-sun:   #FFB830;
    --cb-sky:   #4FC3F7;
    --cb-rose:  #F06292;
    --cb-navy1: #1a2a4a;
    --cb-navy2: #243756;
    --cb-bg:    #F0F7FF;
    --cb-text:  #2D3A4A;
    --cb-muted: #8A9BB0;
  }

  #cb-launcher {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 60px; height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--cb-sky), var(--cb-rose));
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    border: none;
    cursor: pointer;
    font-size: 28px;
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
    transition: transform .2s;
  }
  #cb-launcher:hover { transform: scale(1.08); }

  #cb-window {
    position: fixed;
    bottom: 96px;
    right: 24px;
    width: 340px;
    max-width: calc(100vw - 32px);
    height: 460px;
    max-height: calc(100vh - 140px);
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.25);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
    font-family: 'Nunito', sans-serif;
  }
  #cb-window.open { display: flex; }

  #cb-header {
    background: linear-gradient(135deg, var(--cb-navy1), var(--cb-navy2));
    color: #fff;
    padding: 16px 18px;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
  }
  #cb-header .cb-title { font-weight: 800; font-size: 15px; display: flex; align-items: center; gap: 8px; }
  #cb-header .cb-sub { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; }
  #cb-close {
    background: rgba(255,255,255,0.15);
    border: none; color: #fff;
    width: 28px; height: 28px; border-radius: 50%;
    cursor: pointer; font-size: 16px;
    display: flex; align-items: center; justify-content: center;
  }
  #cb-close:hover { background: rgba(255,255,255,0.3); }

  #cb-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: var(--cb-bg);
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .cb-msg {
    max-width: 82%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 13.5px;
    line-height: 1.4;
  }
  .cb-msg.bot {
    background: #fff;
    color: var(--cb-text);
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  }
  .cb-msg.user {
    background: var(--cb-sky);
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
  }

  .cb-chip-row {
    display: flex; flex-wrap: wrap; gap: 6px;
    margin-top: 4px;
  }
  .cb-chip {
    background: #fff;
    border: 1px solid #E0E8F0;
    color: var(--cb-text);
    font-size: 12px; font-weight: 700;
    padding: 6px 12px;
    border-radius: 20px;
    cursor: pointer;
    transition: all .15s;
  }
  .cb-chip:hover { background: var(--cb-sky); color: #fff; border-color: var(--cb-sky); }

  #cb-inputRow {
    display: flex; gap: 8px;
    padding: 12px;
    border-top: 1px solid #eee;
    background: #fff;
    flex-shrink: 0;
  }
  #cb-input {
    flex: 1;
    border: 1px solid #E0E8F0;
    border-radius: 20px;
    padding: 10px 14px;
    font-size: 13px;
    outline: none;
    font-family: inherit;
  }
  #cb-input:focus { border-color: var(--cb-sky); }
  #cb-send {
    background: var(--cb-sky);
    border: none;
    color: #fff;
    width: 38px; height: 38px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    flex-shrink: 0;
  }
  #cb-send:hover { background: var(--cb-rose); }

  @media (max-width: 420px) {
    #cb-window { right: 12px; bottom: 88px; width: calc(100vw - 24px); }
    #cb-launcher { right: 16px; bottom: 16px; }
  }
</style>

<button id="cb-launcher" title="Chat with us">💬</button>

<div id="cb-window">
  <div id="cb-header">
    <div>
      <div class="cb-title">🌟 Little Stars Assistant</div>
      <div class="cb-sub">Ask about fees, timings, admissions...</div>
    </div>
    <button id="cb-close">✕</button>
  </div>
  <div id="cb-messages"></div>
  <div id="cb-inputRow">
    <input type="text" id="cb-input" placeholder="Type your question..." autocomplete="off">
    <button id="cb-send">➤</button>
  </div>
</div>

<script>
(function(){
  const faqData = <?= $faqJson ?>;
  const launcher = document.getElementById('cb-launcher');
  const win = document.getElementById('cb-window');
  const closeBtn = document.getElementById('cb-close');
  const messages = document.getElementById('cb-messages');
  const input = document.getElementById('cb-input');
  const sendBtn = document.getElementById('cb-send');

  let started = false;

  launcher.addEventListener('click', () => {
    win.classList.toggle('open');
    if (win.classList.contains('open') && !started) {
      started = true;
      greet();
    }
  });
  closeBtn.addEventListener('click', () => win.classList.remove('open'));

  function addMsg(text, from) {
    const div = document.createElement('div');
    div.className = 'cb-msg ' + from;
    div.textContent = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  function addChips() {
    const row = document.createElement('div');
    row.className = 'cb-chip-row';
    faqData.slice(0, 5).forEach(item => {
      const chip = document.createElement('button');
      chip.className = 'cb-chip';
      chip.textContent = item.question;
      chip.onclick = () => {
        addMsg(item.question, 'user');
        setTimeout(() => addMsg(item.answer, 'bot'), 300);
      };
      row.appendChild(chip);
    });
    messages.appendChild(row);
    messages.scrollTop = messages.scrollHeight;
  }

  function greet() {
    addMsg("Hi! 👋 I'm the Little Stars assistant. Ask me about fees, timings, admissions, classes, or anything else — or tap a question below.", 'bot');
    addChips();
  }

  function findAnswer(text) {
    const lower = text.toLowerCase();
    let best = null;
    let bestScore = 0;
    faqData.forEach(item => {
      let score = 0;
      item.keywords.forEach(k => {
        if (lower.includes(k)) score++;
      });
      if (score > bestScore) { bestScore = score; best = item; }
    });
    return best;
  }

  function handleSend() {
    const text = input.value.trim();
    if (!text) return;
    addMsg(text, 'user');
    input.value = '';

    setTimeout(() => {
      const match = findAnswer(text);
      if (match) {
        addMsg(match.answer, 'bot');
      } else {
        addMsg("Sorry, I don't have an answer for that yet. Please contact the school office directly, or try asking about fees, timings, admissions, or classes.", 'bot');
        addChips();
      }
    }, 300);
  }

  sendBtn.addEventListener('click', handleSend);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') handleSend();
  });
})();
</script>
