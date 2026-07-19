<?php
/*
=====================================================================
 Little Stars Pre School — Parent FAQ Chatbot Widget
=====================================================================
 HOW TO USE:
 1. Save this file as "chatbot-widget.php" inside your project
    (e.g. same folder as parents.php).
 2. Open any PUBLIC page (index.php, about.php, contact.php, etc.)
    and add this line right before the closing </body> tag:

        <?php include 'chatbot-widget.php'; ?>

 3. Done. A floating chat button will appear bottom-right on that
    page. No login required, no database needed — this is a
    simple rule-based (keyword matching) FAQ bot that runs
    entirely in the browser.

 TO EDIT THE ANSWERS:
   Scroll down to the "FAQ_DATA" JavaScript array below and edit
   the question/keywords/answer for each entry, or add new ones
   by copying an existing block.
=====================================================================
*/
?>
<!-- ============== LITTLE STARS FAQ CHATBOT WIDGET ============== -->
<style>
  :root{
    --ls-navy:#1b2338;
    --ls-navy-light:#252e47;
    --ls-orange:#e2622b;
    --ls-orange-light:#f4a15c;
    --ls-cream:#fdf8f2;
    --ls-text:#2b2b2b;
    --ls-muted:#8a8f9c;
  }

  #ls-chat-launcher{
    position:fixed;
    right:24px;
    bottom:24px;
    width:64px;
    height:64px;
    border-radius:50%;
    background:linear-gradient(145deg,var(--ls-orange-light),var(--ls-orange));
    box-shadow:0 8px 24px rgba(226,98,43,0.4), 0 2px 6px rgba(0,0,0,0.15);
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    z-index:99998;
    border:none;
    transition:transform .2s ease, box-shadow .2s ease;
  }
  #ls-chat-launcher:hover{ transform:translateY(-3px) scale(1.04); }
  #ls-chat-launcher svg{ width:28px; height:28px; }
  #ls-chat-launcher .ls-ping{
    position:absolute; top:-3px; right:-3px;
    width:16px; height:16px; border-radius:50%;
    background:#ff4d4f; border:2px solid var(--ls-cream);
  }

  #ls-chat-window{
    position:fixed;
    right:24px;
    bottom:100px;
    width:360px;
    max-width:92vw;
    height:520px;
    max-height:75vh;
    background:var(--ls-cream);
    border-radius:20px;
    box-shadow:0 20px 60px rgba(20,20,30,0.25);
    display:none;
    flex-direction:column;
    overflow:hidden;
    z-index:99999;
    font-family:'Segoe UI', system-ui, -apple-system, sans-serif;
  }
  #ls-chat-window.ls-open{ display:flex; animation:ls-rise .25s ease; }
  @keyframes ls-rise{ from{opacity:0; transform:translateY(16px);} to{opacity:1; transform:translateY(0);} }

  #ls-chat-header{
    background:var(--ls-navy);
    background-image:radial-gradient(circle at 90% -10%, var(--ls-navy-light), var(--ls-navy) 60%);
    color:#fff;
    padding:16px 18px;
    display:flex;
    align-items:center;
    gap:10px;
  }
  #ls-chat-header .ls-avatar{
    width:38px; height:38px; border-radius:12px;
    background:linear-gradient(145deg,var(--ls-orange-light),var(--ls-orange));
    display:flex; align-items:center; justify-content:center;
    font-size:20px; flex-shrink:0;
  }
  #ls-chat-header .ls-title{ font-weight:700; font-size:15px; line-height:1.2; }
  #ls-chat-header .ls-sub{ font-size:12px; color:#a9b0c3; display:flex; align-items:center; gap:5px; }
  #ls-chat-header .ls-dot{ width:7px; height:7px; border-radius:50%; background:#4ade80; display:inline-block; }
  #ls-chat-close{
    margin-left:auto; background:none; border:none; color:#c7cbd8;
    font-size:20px; cursor:pointer; line-height:1; padding:4px;
  }
  #ls-chat-close:hover{ color:#fff; }

  #ls-chat-body{
    flex:1;
    overflow-y:auto;
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:10px;
    background:
      radial-gradient(circle at 15% 10%, rgba(226,98,43,0.05), transparent 40%),
      var(--ls-cream);
  }
  .ls-msg{ max-width:82%; padding:10px 13px; border-radius:14px; font-size:13.5px; line-height:1.45; }
  .ls-msg.bot{
    background:#fff; color:var(--ls-text);
    border:1px solid #eee2d6;
    align-self:flex-start;
    border-bottom-left-radius:4px;
    box-shadow:0 1px 2px rgba(0,0,0,0.03);
  }
  .ls-msg.user{
    background:var(--ls-navy); color:#fff;
    align-self:flex-end;
    border-bottom-right-radius:4px;
  }

  .ls-quick-wrap{ display:flex; flex-wrap:wrap; gap:6px; align-self:flex-start; max-width:100%; }
  .ls-quick-btn{
    background:#fff;
    border:1px solid var(--ls-orange);
    color:var(--ls-orange);
    padding:6px 11px;
    border-radius:20px;
    font-size:12.5px;
    cursor:pointer;
    transition:background .15s, color .15s;
    white-space:nowrap;
  }
  .ls-quick-btn:hover{ background:var(--ls-orange); color:#fff; }

  #ls-chat-input-row{
    display:flex; gap:8px; padding:12px;
    border-top:1px solid #eee2d6; background:#fff;
  }
  #ls-chat-input{
    flex:1; border:1px solid #e5ddd0; border-radius:22px;
    padding:10px 15px; font-size:13.5px; outline:none;
    background:var(--ls-cream);
  }
  #ls-chat-input:focus{ border-color:var(--ls-orange); }
  #ls-chat-send{
    width:40px; height:40px; border-radius:50%; border:none;
    background:var(--ls-orange); color:#fff; cursor:pointer;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  #ls-chat-send:hover{ background:#c94f1c; }

  #ls-chat-body::-webkit-scrollbar{ width:6px; }
  #ls-chat-body::-webkit-scrollbar-thumb{ background:#e5ddd0; border-radius:3px; }

  @media (max-width:480px){
    #ls-chat-window{ right:12px; left:12px; width:auto; bottom:90px; }
    #ls-chat-launcher{ right:16px; bottom:16px; }
  }
</style>

<button id="ls-chat-launcher" aria-label="Open FAQ chat">
  <span class="ls-ping"></span>
  <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
  </svg>
</button>

<div id="ls-chat-window">
  <div id="ls-chat-header">
    <div class="ls-avatar">⭐</div>
    <div>
      <div class="ls-title">Little Stars Help Desk</div>
      <div class="ls-sub"><span class="ls-dot"></span> Instant answers for parents</div>
    </div>
    <button id="ls-chat-close" aria-label="Close chat">✕</button>
  </div>
  <div id="ls-chat-body"></div>
  <div id="ls-chat-input-row">
    <input id="ls-chat-input" type="text" placeholder="Type your question…" autocomplete="off" />
    <button id="ls-chat-send" aria-label="Send">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"></line>
        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
      </svg>
    </button>
  </div>
</div>

<script>
(function(){
  // ---------------------------------------------------------------
  // EDIT YOUR FAQ ANSWERS HERE
  // Each entry: question (shown as quick-reply), keywords (words that
  // trigger it when typed), answer (the bot's reply).
  // ---------------------------------------------------------------
  var FAQ_DATA = [
    {
      question: "How do I enroll my child?",
      keywords: ["enroll","enrol","admission","register","registration","join","apply"],
      answer: "To enroll your child, visit our office with your child's birth certificate and immunization record, or call us to schedule a visit. Our admissions team will guide you through the rest!"
    },
    {
      question: "What are the school hours?",
      keywords: ["hours","time","open","close","timing","schedule"],
      answer: "Little Stars is open Monday–Friday, 7:30 AM to 5:00 PM. Regular class hours are 8:30 AM to 1:00 PM, with extended daycare available until 5:00 PM."
    },
    {
      question: "How do I pay fees?",
      keywords: ["fee","fees","pay","payment","invoice","bill","cost","price"],
      answer: "Fees can be paid via bank transfer, card, or cash at the office. You can also check your payment history under the Payments section of the parent portal."
    },
    {
      question: "How can I check attendance?",
      keywords: ["attendance","absent","present","leave"],
      answer: "Daily attendance is recorded by teachers each morning. Parents can view attendance records through the parent portal, or ask your child's class teacher directly."
    },
    {
      question: "What activities does the school offer?",
      keywords: ["activity","activities","program","sports","art","music","play"],
      answer: "We offer a mix of art, music, storytelling, outdoor play, and early-learning activities designed for each age group. Check the Activities section for the current term's schedule."
    },
    {
      question: "How do I contact a teacher?",
      keywords: ["teacher","contact","talk","meet","reach","call"],
      answer: "You can reach your child's teacher through the front office, or leave a message via the parent portal and the teacher will get back to you within a school day."
    },
    {
      question: "What are the holidays this term?",
      keywords: ["holiday","holidays","break","vacation","closed"],
      answer: "Our holiday calendar is posted on the notice board and shared with parents at the start of each term. Please check with the office for the most current list of upcoming holidays."
    }
  ];

  var FALLBACK = "I don't have an answer for that just yet. Please contact the school office directly and our staff will be happy to help!";
  var GREETING = "Hi there! 👋 I'm the Little Stars Help Desk. Ask me about admissions, fees, hours, attendance, or activities — or tap a question below.";

  var launcher = document.getElementById('ls-chat-launcher');
  var win = document.getElementById('ls-chat-window');
  var closeBtn = document.getElementById('ls-chat-close');
  var body = document.getElementById('ls-chat-body');
  var input = document.getElementById('ls-chat-input');
  var sendBtn = document.getElementById('ls-chat-send');
  var started = false;

  function addMessage(text, sender){
    var el = document.createElement('div');
    el.className = 'ls-msg ' + sender;
    el.textContent = text;
    body.appendChild(el);
    body.scrollTop = body.scrollHeight;
  }

  function addQuickReplies(){
    var wrap = document.createElement('div');
    wrap.className = 'ls-quick-wrap';
    FAQ_DATA.forEach(function(item){
      var btn = document.createElement('button');
      btn.className = 'ls-quick-btn';
      btn.textContent = item.question;
      btn.onclick = function(){ handleUserMessage(item.question); };
      wrap.appendChild(btn);
    });
    body.appendChild(wrap);
    body.scrollTop = body.scrollHeight;
  }

  function findAnswer(text){
    var lower = text.toLowerCase();
    for (var i = 0; i < FAQ_DATA.length; i++){
      var item = FAQ_DATA[i];
      for (var j = 0; j < item.keywords.length; j++){
        if (lower.indexOf(item.keywords[j]) !== -1){
          return item.answer;
        }
      }
    }
    return FALLBACK;
  }

  function handleUserMessage(text){
    text = text.trim();
    if (!text) return;
    addMessage(text, 'user');
    input.value = '';
    setTimeout(function(){
      addMessage(findAnswer(text), 'bot');
    }, 350);
  }

  launcher.addEventListener('click', function(){
    win.classList.add('ls-open');
    if (!started){
      started = true;
      addMessage(GREETING, 'bot');
      addQuickReplies();
    }
    input.focus();
  });

  closeBtn.addEventListener('click', function(){
    win.classList.remove('ls-open');
  });

  sendBtn.addEventListener('click', function(){ handleUserMessage(input.value); });
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter') handleUserMessage(input.value);
  });
})();
</script>
<!-- ============ END LITTLE STARS FAQ CHATBOT WIDGET ============ -->
