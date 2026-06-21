// ============================================
// JavaScript الرئيسي لمزاد البناء
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // إغلاق القوائم عند النقر خارجها
    document.addEventListener('click', function(e) {
        // إغلاق قائمة المستخدم
        const userMenu = document.getElementById('user-menu');
        const userBtn = e.target.closest('[onclick="toggleUserMenu()"]');
        if (userMenu && !userMenu.classList.contains('hidden') && !userBtn && !e.target.closest('#user-menu')) {
            userMenu.classList.add('hidden');
        }
        
        // إغلاق الإشعارات
        const notifDropdown = document.getElementById('notif-dropdown');
        const notifBtn = e.target.closest('[onclick="toggleNotifications()"]');
        if (notifDropdown && !notifDropdown.classList.contains('hidden') && !notifBtn && !e.target.closest('#notif-dropdown')) {
            notifDropdown.classList.add('hidden');
        }
    });
});

// ============================================
// تحميل المدن بناءً على الدولة
// ============================================
function loadCities(countrySelectId, citySelectId) {
    const countrySelect = document.getElementById(countrySelectId);
    const citySelect = document.getElementById(citySelectId);
    const countryId = countrySelect.value;
    
    // تفريغ المدن
    citySelect.innerHTML = '<option value="">جاري التحميل...</option>';
    citySelect.disabled = true;
    
    if (!countryId) {
        citySelect.innerHTML = '<option value="">اختر الدولة أولاً</option>';
        citySelect.disabled = false;
        return;
    }
    
    // جلب المدن عبر AJAX
    fetch(`api.php?action=get_cities&country_id=${countryId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                citySelect.innerHTML = '<option value="">اختر المدينة</option>';
                
                if (data.status === 'success' && data.data.length > 0) {
                    data.data.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name_ar;
                        citySelect.appendChild(option);
                    });
                } else {
                    citySelect.innerHTML = '<option value="">لا توجد مدن متاحة</option>';
                }
            } catch (e) {
                console.error('JSON Parse Error. Server Response:', text);
                citySelect.innerHTML = '<option value="">خطأ في بيانات السيرفر</option>';
                showToast('خطأ في استجابة السيرفر، راجع الكونسول', 'error');
            }
            citySelect.disabled = false;
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            citySelect.innerHTML = '<option value="">حدث خطأ في الاتصال</option>';
            citySelect.disabled = false;
        });
    
    // تحديث العملة
    updateCurrency(countryId);
}

// تحديث رمز العملة
function updateCurrency(countryId) {
    fetch(`api.php?action=get_currency&country_id=${countryId}`)
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.status === 'success' && data.data) {
                    const currencyEl = document.getElementById('currency-display');
                    if (currencyEl) {
                        currencyEl.textContent = data.data.currency_ar + ' (' + data.data.currency_code + ')';
                    }
                }
            } catch (e) {
                console.error('Currency JSON Error. Server Response:', text);
            }
        })
        .catch(error => console.error('Currency Fetch Error:', error));
}

// ============================================
// إظهار/إخفاء الحقول حسب نوع المستخدم (معدل)
// ============================================
function toggleUserTypeFields() {
    const userTypeRadio = document.querySelector('input[name="user_type"]:checked');
    const shopFields = document.getElementById('shop-fields');
    const contractorFields = document.getElementById('contractor-fields');
    const entityFields = document.getElementById('entity-fields');  // حقل فرد أو شركة
    
    // إخفاء كل الحقول أولاً
    if (shopFields) shopFields.classList.add('hidden');
    if (contractorFields) contractorFields.classList.add('hidden');
    if (entityFields) entityFields.classList.add('hidden');
    
    if (userTypeRadio) {
        const userType = userTypeRadio.value;
        
        // للمحل: تظهر معلومات المحل
        if (userType === 'shop' && shopFields) {
            shopFields.classList.remove('hidden');
        }
        
        // للمقاول: تظهر التخصصات
        if (userType === 'contractor' && contractorFields) {
            contractorFields.classList.remove('hidden');
        }
        
        // لصاحب العمل أو المقاول: يظهر حقل فرد/شركة
        if ((userType === 'employer' || userType === 'contractor') && entityFields) {
            entityFields.classList.remove('hidden');
        }
    }
}

// ============================================
// إظهار/إخفاء كلمة المرور
// ============================================
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ============================================
// القوائم المنسدلة
// ============================================
function toggleUserMenu() {
    const menu = document.getElementById('user-menu');
    if (menu) menu.classList.toggle('hidden');
    
    // إغلاق الإشعارات
    const notifDropdown = document.getElementById('notif-dropdown');
    if (notifDropdown) notifDropdown.classList.add('hidden');
}

function toggleNotifications() {
    const dropdown = document.getElementById('notif-dropdown');
    if (dropdown) dropdown.classList.toggle('hidden');
    
    // إغلاق قائمة المستخدم
    const userMenu = document.getElementById('user-menu');
    if (userMenu) userMenu.classList.add('hidden');
}

function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    if (menu) menu.classList.toggle('hidden');
}

// ============================================
// الإشعارات (Toast)
// ============================================
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-5 left-5 z-50 flex flex-col gap-2';
        document.body.appendChild(container);
    }
    
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        warning: 'bg-amber-500',
        info: 'bg-blue-600'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast ${colors[type]} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 min-w-[280px] animate-slide-in`;
    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span class="text-sm font-medium">${message}</span>
    `;
    
    container.appendChild(toast);
    
    // حذف بعد 4 ثواني
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ============================================
// إدارة الإشعارات (الجرس)
// ============================================

// تعيين الكل كمقروء
function markAllRead() {
    const badge = document.getElementById('notif-badge');
    if (badge) badge.classList.add('hidden');
    
    fetch('api.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                // إزالة الخلفية الزرقاء من كل الإشعارات
                document.querySelectorAll('#notif-list a.bg-blue-50').forEach(el => {
                    el.classList.remove('bg-blue-50');
                    el.classList.add('bg-white');
                });
                showToast('تم تعيين الكل كمقروء', 'success');
            }
        })
        .catch(error => console.error('Mark All Read Error:', error));
}

// تعيين إشعار واحد كمقروء عند الضغط عليه
function markNotifRead(notifId, element) {
    fetch(`api.php?action=mark_read&notif_id=${notifId}`)
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                // إزالة الخلفية الزرقاء من الإشعار الذي تم الضغط عليه
                if(element) {
                    element.classList.remove('bg-blue-50');
                    element.classList.add('bg-white');
                }
                
                // تحديث رقم الإشعارات في الجرس
                const badge = document.getElementById('notif-badge');
                if (badge) {
                    let count = parseInt(badge.textContent) - 1;
                    if (count > 0) {
                        badge.textContent = count;
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }
        })
        .catch(error => console.error('Mark Read Error:', error));
}

// ============================================
// فلترة المشاريع في الصفحة الرئيسية
// ============================================
function filterProjects(category) {
    // تحديث أزرار الفلتر
    document.querySelectorAll('.cat-filter').forEach(btn => {
        btn.classList.remove('bg-primary-600', 'text-white');
        btn.classList.add('bg-white', 'text-gray-600', 'border', 'border-gray-200');
    });
    
    const activeBtn = document.querySelector(`.cat-filter[data-cat="${category}"]`);
    if (activeBtn) {
        activeBtn.classList.add('bg-primary-600', 'text-white');
        activeBtn.classList.remove('bg-white', 'text-gray-600', 'border', 'border-gray-200');
    }
    
    // فلترة البطاقات
    const cards = document.querySelectorAll('.project-card');
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// ============================================
// التحقق من نموذج التسجيل (معدل)
// ============================================
function validateRegisterForm() {
    const form = document.getElementById('register-form');
    if (!form) return true;
    
    const userType = form.querySelector('input[name="user_type"]:checked');
    
    if (!userType) {
        showToast('يرجى اختيار نوع الحساب', 'error');
        return false;
    }
    
    const password = form.querySelector('input[name="password"]').value;
    const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirmPassword) {
        showToast('كلمتا المرور غير متطابقتين', 'error');
        return false;
    }
    
    if (password.length < 6) {
        showToast('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'error');
        return false;
    }
    
    // التحقق من entity_type (فرد أو شركة) لأصحاب العمل والمقاولين
    if (userType.value === 'employer' || userType.value === 'contractor') {
        const entityType = form.querySelector('input[name="entity_type"]:checked');
        if (!entityType) {
            showToast('يرجى اختيار تصنيف الحساب (فرد أو شركة)', 'error');
            return false;
        }
    }
    
    if (userType.value === 'shop') {
        const shopName = form.querySelector('input[name="shop_name"]');
        if (shopName && !shopName.value.trim()) {
            showToast('يرجى إدخال اسم المحل', 'error');
            return false;
        }
    }
    
    return true;
}

// ============================================
// رفع صور متعددة (معاينة)
// ============================================
function previewImages(input, previewContainerId) {
    const container = document.getElementById(previewContainerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (input.files) {
        Array.from(input.files).forEach((file, index) => {
            if (!file.type.startsWith('image/')) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'relative group';
                div.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-32 object-cover rounded-lg border border-gray-200">
                    <button type="button" onclick="this.parentElement.remove()" class="absolute top-1 left-1 bg-red-500 text-white w-6 h-6 rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
}

// ============================================
// إضافة CSS للأنيميشن
// ============================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(-100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(-100%);
            opacity: 0;
        }
    }
    
    .animate-slide-in {
        animation: slideIn 0.3s ease-out forwards;
    }
`;
document.head.appendChild(style);