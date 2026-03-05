/* ============================================================
   Course shared JavaScript — Navigation, Quiz, Copy buttons
   ============================================================ */

// ── NAVIGATION DATA ──────────────────────────────────────────
const LESSONS = [
  { id: 0,  file: 'lesson-00.html', title: 'Environment Setup',         ar: 'إعداد بيئة العمل' },
  { id: 1,  file: 'lesson-01.html', title: 'FastAPI Backend',           ar: 'بناء الـ Backend' },
  { id: 2,  file: 'lesson-02.html', title: 'Database Design',           ar: 'تصميم قاعدة البيانات' },
  { id: 3,  file: 'lesson-03.html', title: 'Zoho API & Webhooks',       ar: 'Zoho API والـ Webhooks' },
  { id: 4,  file: 'lesson-04.html', title: 'Moodle Plugin',             ar: 'إضافة Moodle' },
  { id: 5,  file: 'lesson-05.html', title: 'Data Flow & Field Mapping', ar: 'تدفق البيانات' },
  { id: 6,  file: 'lesson-06.html', title: 'Testing & Debugging',       ar: 'الاختبار والتصحيح' },
  { id: 7,  file: 'lesson-07.html', title: 'Production Deployment',     ar: 'نشر في الإنتاج' },
  { id: 8,  file: 'lesson-08.html', title: 'CI/CD Pipeline',            ar: 'التكامل المستمر' },
  { id: 9,  file: 'lesson-09.html', title: 'Conclusion & Next Steps',   ar: 'الخاتمة' },
];

// ── BUILD SIDEBAR ─────────────────────────────────────────────
function buildSidebar(currentLesson) {
  const nav = document.getElementById('sidebar-nav');
  if (!nav) return;

  const progress = JSON.parse(localStorage.getItem('courseProgress') || '{}');

  LESSONS.forEach(lesson => {
    const isDone    = progress[lesson.id] === 'done';
    const isActive  = lesson.id === currentLesson;
    const a = document.createElement('a');
    a.href  = lesson.file;
    a.className = 'nav-item' + (isActive ? ' active' : '') + (isDone && !isActive ? ' done' : '');
    a.innerHTML = `
      <span class="lesson-num">${isDone && !isActive ? '✓' : lesson.id}</span>
      <span>${lesson.title}</span>
    `;
    nav.appendChild(a);
  });
}

// ── MARK LESSON AS DONE & BUILD NAV FOOTER ──────────────────
function initLesson(currentId) {
  // Mark current as done when page loads
  const progress = JSON.parse(localStorage.getItem('courseProgress') || '{}');
  progress[currentId] = 'done';
  localStorage.setItem('courseProgress', JSON.stringify(progress));

  buildSidebar(currentId);
  buildNavFooter(currentId);
}

function buildNavFooter(currentId) {
  const footer = document.getElementById('lesson-nav');
  if (!footer) return;

  const prev = LESSONS[currentId - 1];
  const next = LESSONS[currentId + 1];

  footer.innerHTML = `
    <div>
      ${prev ? `<a href="${prev.file}" class="nav-btn">← ${prev.title}</a>` : `<a href="index.html" class="nav-btn">← Course Home</a>`}
    </div>
    <div class="lesson-progress">
      <div>Lesson ${currentId + 1} of ${LESSONS.length}</div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:${((currentId + 1) / LESSONS.length * 100).toFixed(0)}%"></div>
      </div>
    </div>
    <div>
      ${next ? `<a href="${next.file}" class="nav-btn primary">${next.title} →</a>` : `<a href="index.html" class="nav-btn primary">🎓 Complete!</a>`}
    </div>
  `;
}

// ── COPY BUTTONS ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const pre = btn.closest('.code-block').querySelector('pre');
      navigator.clipboard.writeText(pre.innerText.trim()).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Copied!';
        setTimeout(() => btn.textContent = orig, 1800);
      });
    });
  });
});

// ── QUIZ LOGIC ───────────────────────────────────────────────
function initQuizzes() {
  document.querySelectorAll('.quiz-card').forEach(card => {
    const submitBtn = card.querySelector('.quiz-submit');
    if (!submitBtn) return;

    submitBtn.addEventListener('click', () => {
      const selected = card.querySelector('input[type=radio]:checked');
      const feedback = card.querySelector('.quiz-feedback');
      if (!selected) {
        alert('Please select an answer! / الرجاء اختيار إجابة!');
        return;
      }

      const correct = selected.dataset.correct === 'true';
      const options = card.querySelectorAll('.quiz-option');
      options.forEach(opt => {
        const input = opt.querySelector('input');
        if (input.dataset.correct === 'true') opt.classList.add('correct');
        else if (input.checked) opt.classList.add('wrong');
        input.disabled = true;
      });

      if (feedback) {
        feedback.classList.add('show');
        feedback.classList.add(correct ? 'correct-msg' : 'wrong-msg');
      }
      submitBtn.disabled = true;
    });
  });
}

document.addEventListener('DOMContentLoaded', initQuizzes);

// ── COURSE APP (used by lesson-00 through lesson-09 new templates) ──────────
const CourseApp = {
  /**
   * Call when DocumentLoaded — sets up click handlers on .quiz-options li
   * based on data-choice attributes and data-answer on .quiz-question.
   */
  initProgress() {
    document.querySelectorAll('.quiz-options li[data-choice]').forEach(li => {
      li.addEventListener('click', () => {
        const question = li.closest('.quiz-question');
        if (!question || question.dataset.answered) return;
        question.querySelectorAll('.quiz-options li').forEach(opt => opt.classList.remove('selected'));
        li.classList.add('selected');
      });
    });
  },

  /**
   * Evaluate all quiz questions within the nearest .quiz-section ancestor
   * of the submit button and show feedback.
   * @param {HTMLElement} btn - the submit button element
   */
  submitQuiz(btn) {
    const section = btn.closest('.quiz-section') || document.getElementById('quiz');
    if (!section) return;

    let total = 0, correct = 0;

    section.querySelectorAll('.quiz-question').forEach(question => {
      if (question.dataset.answered) return;
      const answer   = question.dataset.answer;
      const selected = question.querySelector('.quiz-options li.selected');
      const feedback = question.querySelector('.quiz-feedback');
      total++;

      question.querySelectorAll('.quiz-options li').forEach(li => {
        if (li.dataset.choice === answer) li.classList.add('correct');
      });

      if (selected) {
        if (selected.dataset.choice === answer) {
          selected.classList.add('correct');
          correct++;
          if (feedback) { feedback.textContent = '✓ Correct!'; feedback.style.cssText = 'display:block;padding:0.4rem 0.8rem;margin-top:0.4rem;border-radius:6px;background:#d1fae5;color:#065f46;font-weight:600;'; }
        } else {
          selected.classList.add('wrong');
          if (feedback) { feedback.textContent = '✗ Incorrect — see highlighted correct answer.'; feedback.style.cssText = 'display:block;padding:0.4rem 0.8rem;margin-top:0.4rem;border-radius:6px;background:#fee2e2;color:#991b1b;font-weight:600;'; }
        }
      } else {
        if (feedback) { feedback.textContent = '⚠ No answer selected.'; feedback.style.cssText = 'display:block;padding:0.4rem 0.8rem;margin-top:0.4rem;border-radius:6px;background:#fef3c7;color:#92400e;font-weight:600;'; }
      }

      question.dataset.answered = '1';
      question.querySelectorAll('.quiz-options li').forEach(li => { li.style.pointerEvents = 'none'; li.style.cursor = 'default'; });
    });

    btn.disabled = true;
    btn.textContent = `Score: ${correct}/${total}`;
    btn.style.background = correct === total ? '#10b981' : '#f59e0b';
  },

  markComplete(lessonId) {
    const progress = JSON.parse(localStorage.getItem('courseProgress') || '{}');
    progress[lessonId] = 'done';
    localStorage.setItem('courseProgress', JSON.stringify(progress));
  }
};

document.addEventListener('DOMContentLoaded', () => CourseApp.initProgress());
