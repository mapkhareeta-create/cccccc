<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$other_user_id = (int)($_GET['user_id'] ?? 0);
$bid_id = (int)($_GET['bid_id'] ?? 0);
$project_id = (int)($_GET['project_id'] ?? 0);

if (!$other_user_id) {
    redirect('my_bids.php');
}

// جلب معلومات المستخدم الآخر
$stmt = $pdo->prepare("SELECT id, name, user_type, avatar FROM users WHERE id = ?");
$stmt->execute([$other_user_id]);
$other_user = $stmt->fetch();

if (!$other_user) {
    redirect('my_bids.php');
}

// جلب المحادثات بين المستخدمين
$sql = "
    SELECT m.*, u.name as sender_name, u.avatar as sender_avatar
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
$messages = $stmt->fetchAll();

// تحديث حالة الرسائل كمقروءة
$stmt = $pdo->prepare("
    UPDATE messages SET is_read = 1 
    WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
");
$stmt->execute([$user_id, $other_user_id]);

$page_title = 'محادثة مع ' . clean($other_user['name']);
include 'includes/header.php';
?>

<style>
    .chat-container {
        height: calc(100vh - 250px);
        display: flex;
        flex-direction: column;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .chat-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
        background: #f8fafc;
    }
    
    .chat-header .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .chat-header .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .chat-header .info .name {
        font-weight: 700;
        font-size: 16px;
        color: #0f172a;
    }
    
    .chat-header .info .type {
        font-size: 12px;
        color: #64748b;
    }
    
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: #f8fafc;
    }
    
    .message {
        max-width: 80%;
        padding: 10px 16px;
        border-radius: 12px;
        word-wrap: break-word;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message.sent {
        align-self: flex-end;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    .message.received {
        align-self: flex-start;
        background: white;
        color: #0f172a;
        border-bottom-left-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .message .time {
        font-size: 10px;
        color: rgba(255,255,255,0.6);
        margin-top: 4px;
        display: block;
    }
    
    .message.received .time {
        color: #94a3b8;
    }
    
    .chat-input {
        padding: 16px 20px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 12px;
        background: white;
    }
    
    .chat-input input {
        flex: 1;
        padding: 10px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .chat-input input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
    }
    
    .chat-input button {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .chat-input button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(59,130,246,0.3);
    }
    
    .empty-chat {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
    }
    
    .empty-chat i {
        font-size: 48px;
        margin-bottom: 12px;
        color: #cbd5e1;
    }
</style>

<div class="py-4">
    <div class="max-w-4xl mx-auto px-4">
        <div class="chat-container">
            <!-- Header -->
            <div class="chat-header">
                <div class="avatar">
                    <?php if (!empty($other_user['avatar']) && $other_user['avatar'] !== 'default-avatar.png'): ?>
                    <img src="<?= SITE_URL ?>/<?= $other_user['avatar'] ?>" alt="<?= clean($other_user['name']) ?>">
                    <?php else: ?>
                    <i class="fas fa-user text-gray-400"></i>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <div class="name"><?= clean($other_user['name']) ?></div>
                    <div class="type">
                        <?php
                            $user_types = [
                                'employer' => 'صاحب عمل',
                                'contractor' => 'مقاول',
                                'shop' => 'محل مواد',
                                'admin' => 'أدمن'
                            ];
                            echo $user_types[$other_user['user_type']] ?? $other_user['user_type'];
                        ?>
                    </div>
                </div>
                <a href="my_bids.php" class="mr-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </a>
            </div>
            
            <!-- Messages -->
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                <div class="empty-chat">
                    <i class="fas fa-comment-dots"></i>
                    <p>لا توجد رسائل بعد</p>
                    <p class="text-sm">ابدأ المحادثة الآن</p>
                </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                        <?= clean($msg['message']) ?>
                        <span class="time"><?= date('h:i A', strtotime($msg['created_at'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Input -->
            <div class="chat-input">
                <input type="text" id="messageInput" placeholder="اكتب رسالتك..." onkeypress="if(event.key==='Enter') sendMessage()">
                <button onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i> إرسال
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    const messagesContainer = document.getElementById('chatMessages');
    
    // إضافة الرسالة محلياً
    const msgDiv = document.createElement('div');
    msgDiv.className = 'message sent';
    msgDiv.innerHTML = message + '<span class="time">الآن</span>';
    messagesContainer.appendChild(msgDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    input.value = '';
    
    // إرسال الرسالة للخادم
    fetch('api.php?action=send_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            receiver_id: <?= $other_user_id ?>,
            message: message,
            bid_id: <?= $bid_id ?>,
            project_id: <?= $project_id ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('✅ Message sent');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// التمرير للأسفل
document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;

// تحديث الرسائل كل 5 ثواني
setInterval(() => {
    fetch('api.php?action=get_messages&user_id=<?= $other_user_id ?>')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                // تحديث الرسائل الجديدة فقط
            }
        });
}, 5000);
</script>

<?php include 'includes/footer.php'; ?>