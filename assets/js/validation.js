const FormValidator = {
  // นิพจน์เรกิวลาร์ (Regular Expressions)
  patterns: {
    email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
    // รหัสผ่านอย่างน้อย 8 ตัว มีตัวอักษรและตัวเลข
    password: /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{8,}$/,
    // ตัวเลขจำนวนเงิน (ทศนิยมไม่เกิน 2 ตำแหน่ง)
    decimal: /^\d+(\.\d{1,2})?$/
  },

  // ตรวจสอบอีเมล
  validateEmail(email) {
    return this.patterns.email.test(email.trim());
  },

  // ตรวจสอบความปลอดภัยรหัสผ่าน
  validatePassword(password) {
    return this.patterns.password.test(password);
  },

  // ตรวจสอบจำนวนเงิน
  validateAmount(amount) {
    return this.patterns.decimal.test(amount) && parseFloat(amount) > 0;
  },

  // ฟังก์ชันแสดงข้อความแจ้งเตือนความผิดพลาด
  showError(inputElement, message) {
    let errorBox = inputElement.parentElement.querySelector(".error-feedback");
    if (!errorBox) {
      errorBox = document.createElement("small");
      errorBox.className = "error-feedback";
      errorBox.style.color = "var(--danger-color, #ef4444)";
      errorBox.style.marginTop = "0.25rem";
      inputElement.parentElement.appendChild(errorBox);
    }
    errorBox.textContent = message;
    inputElement.style.borderColor = "var(--danger-color, #ef4444)";
  },

  // ฟังก์ชันล้างข้อผิดพลาดเมื่อกรอกถูกต้อง
  clearError(inputElement) {
    const errorBox = inputElement.parentElement.querySelector(".error-feedback");
    if (errorBox) {
      errorBox.remove();
    }
    inputElement.style.borderColor = "var(--border-color, #e5e7eb)";
  }
};