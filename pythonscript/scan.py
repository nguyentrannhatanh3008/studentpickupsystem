import json
import re
from gtts import gTTS
import os
import pygame
import cv2
import queue
import threading
import tkinter as tk
from tkinter import messagebox
from tkinter import ttk
from PIL import Image, ImageTk
import time
from pyzbar import pyzbar
import psycopg2
import datetime
import tempfile
from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
from flask_socketio import SocketIO, emit
import logging

# --------------------------- Configuration ----------------------------

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'database': 'studentpickup',
    'user': 'postgres',
    'password': '!xNq!TRWY.AuD9U'  # Thay bằng mật khẩu thực tế của bạn
}

# Replacement dictionary with regex for word boundaries and case insensitivity
REPLACEMENT_DICT = {
    r'\bXH\b': 'Xã Hội',
    r'\bTN\b': 'Tự Nhiên'
}

def number_to_vietnamese_word(number):
    units = ["", "một", "hai", "ba", "bốn", "năm", "sáu", "bảy", "tám", "chín"]
    tens = ["", "mười", "hai mươi", "ba mươi", "bốn mươi", "năm mươi",
            "sáu mươi", "bảy mươi", "tám mươi", "chín mươi"]

    if number == 0:
        return "không"
    elif number <= 10:
        return ["không", "một", "hai", "ba", "bốn", "năm", "sáu", "bảy", "tám", "chín", "mười"][number]
    elif number < 20:
        return "mười " + units[number % 10]
    elif number < 100:
        ten = number // 10
        unit = number % 10
        return tens[ten] + (" " + units[unit] if unit != 0 else "")
    else:
        return str(number)  # Nếu số lớn hơn 99, trả về số dưới dạng chuỗi

# Initialize pygame mixer
pygame.mixer.init()

# --------------------------- Global Variables ---------------------------

pickup_queue = queue.Queue()  # Định nghĩa pickup_queue ở mức toàn cục
audio_queue = queue.Queue()
is_playing = False
lock = threading.Lock()

app = Flask(__name__)
CORS(app)  # Enable CORS to allow requests from PHP frontend

socketio = SocketIO(app, cors_allowed_origins="*")  # Initialize SocketIO

# Set to track scanned students to prevent duplicates
scanned_students = set()

# Cooldown mechanism for replay to prevent multiple plays within a short period
last_replay_times = {}  # Dictionary to track last replay times
replay_lock = threading.Lock()  # Lock for thread safety
COOLDOWN_PERIOD = 60  # Cooldown period in seconds

# Set to track already processed pickup_ids to prevent duplication from polling and SocketIO
processed_pickups = set()

# --------------------------- Utility Functions ---------------------------

def replace_abbreviations(text, replacement_dict):
    # Thay thế các từ viết tắt thông thường
    for pattern, replacement in replacement_dict.items():
        text = re.sub(pattern, replacement, text, flags=re.IGNORECASE)

    # Thay thế các mẫu số và chữ viết tắt (ví dụ: 11TN, 11XH)
    def replace_number_abbreviation(match):
        number = int(match.group(1))
        abbreviation = match.group(2).upper()
        number_word = number_to_vietnamese_word(number)
        abbreviation_full = {'TN': 'Tự Nhiên', 'XH': 'Xã Hội'}.get(abbreviation, abbreviation)
        return f"{number_word} {abbreviation_full}"

    text = re.sub(r'\b(\d+)(TN|XH)\b', replace_number_abbreviation, text, flags=re.IGNORECASE)

    return text

def enqueue_audio(text, repeat=1):
    for _ in range(repeat):
        audio_queue.put(text)

def audio_worker(audio_queue, stop_event):
    """
    Worker thread to process audio queue and play announcements sequentially.
    """
    global is_playing
    while not stop_event.is_set():
        try:
            text = audio_queue.get(timeout=1) 
        except queue.Empty:
            continue 

        if text is None:
            break

        try:
            announcement = replace_abbreviations(text, REPLACEMENT_DICT)
            logging.info(f"Phát lại thông báo: {announcement}")

            # Generate TTS audio
            tts = gTTS(text=announcement, lang='vi')
            with tempfile.NamedTemporaryFile(delete=False, suffix=".mp3") as tf:
                sound_file = tf.name
                tts.save(sound_file)

            # Play audio
            pygame.mixer.music.load(sound_file)
            pygame.mixer.music.play()
            is_playing = True
            while pygame.mixer.music.get_busy():
                if stop_event.is_set():
                    pygame.mixer.music.stop()
                    break
                pygame.time.Clock().tick(10)
            is_playing = False

            # Unload the music to release the file
            pygame.mixer.music.unload()

            # Remove the sound file after ensuring it's unloaded
            os.remove(sound_file)
            logging.info(f"Finished playing: {announcement}")
        except Exception as e:
            logging.error(f"Lỗi khi phát âm thanh: {e}")
        finally:
            try:
                # Ensure the mixer is unloaded before deleting the file
                pygame.mixer.music.unload()
                if os.path.exists(sound_file):
                    os.remove(sound_file)
            except Exception as cleanup_error:
                logging.error(f"Lỗi khi xóa tệp âm thanh: {cleanup_error}")

        audio_queue.task_done()

def get_db_connection():
    """
    Establish and return a database connection.
    """
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        return conn
    except Exception as e:
        logging.error(f"Error connecting to database: {e}")
        return None

def fetch_new_pickups(conn, last_id):
    """
    Fetch pickups with status 'Chờ xử lý' and id greater than last_id.
    """
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT p.id, s.id AS student_id, s.name, s.class, p.created_at
                FROM public.pickup_history p
                JOIN public.student s ON p.student_id = s.id
                WHERE p.status = 'Chờ xử lý' AND p.id > %s
                ORDER BY p.id ASC
            """, (last_id,))
            return cur.fetchall()
    except Exception as e:
        logging.error(f"Error fetching pickups: {e}")
        return []

def update_pickup_status(conn, pickup_id, status):
    """
    Update the status of a pickup and insert a notification.
    Also, remove the student_id from scanned_students set.
    """
    try:
        with conn.cursor() as cur:
            if status in ['Đã đón', 'Đã hủy']:
                # Fetch student_id before updating
                cur.execute("""
                    SELECT student_id FROM public.pickup_history WHERE id = %s
                """, (pickup_id,))
                result = cur.fetchone()
                student_id = result[0] if result else None

                # Update pickup status
                cur.execute("""
                    UPDATE public.pickup_history
                    SET status = %s, pickup_time = NOW()
                    WHERE id = %s
                """, (status, pickup_id))

                # Fetch user_id for notification
                cur.execute("""
                    SELECT user_id FROM public.pickup_history WHERE id = %s
                """, (pickup_id,))
                result = cur.fetchone()
                user_id = result[0] if result else None

                if user_id:
                    # Insert notification
                    if status == 'Đã đón':
                        notification_title = "Đã Được Xác Nhận"
                        notification_message = f"Yêu cầu đón đã được xác nhận."
                    else:
                        notification_title = "Đã Hủy Đón"
                        notification_message = f"Yêu cầu đón đã bị hủy."

                    cur.execute("""
                        INSERT INTO public.notifications (user_id, title, message, status, created_at)
                        VALUES (%s, %s, %s, 'Chưa đọc', NOW())
                    """, (user_id, notification_title, notification_message))

                # Remove student_id from scanned_students set
                if student_id in scanned_students:
                    scanned_students.discard(student_id)

        conn.commit()
    except Exception as e:
        logging.error(f"Error updating pickup status: {e}")
        conn.rollback()

# --------------------------- GUI Setup ---------------------------

def create_gui(pickup_queue, conn, stop_event):
    """
    Create the Tkinter GUI.
    """
    root = tk.Tk()
    root.title("QR Code Scanner & Pickup Manager")
    root.geometry("1000x700")

    # Create Notebook with two tabs
    notebook = ttk.Notebook(root)
    notebook.pack(pady=10, expand=True)

    # Tab 1: Quét mã QR
    scan_frame = tk.Frame(notebook)
    notebook.add(scan_frame, text="Quét mã QR")

    # Video display label
    video_label = tk.Label(scan_frame)
    video_label.pack()

    # Tab 2: Danh sách chờ
    queue_frame = tk.Frame(notebook)
    notebook.add(queue_frame, text="Danh sách chờ")

    # Treeview for waiting list
    columns = ("Pickup ID", "Tên Học Sinh", "Lớp", "Thời gian", "Trạng thái")
    treeview = ttk.Treeview(queue_frame, columns=columns, show="headings", selectmode="extended")
    for col in columns:
        treeview.heading(col, text=col)
        if col == "Trạng thái":
            treeview.column(col, width=150, anchor='center')
        else:
            treeview.column(col, width=150, anchor='center')
    treeview.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)

    # Buttons Frame
    button_frame = tk.Frame(queue_frame)
    button_frame.pack(pady=10)

    # Buttons
    confirm_btn = tk.Button(button_frame, text="Xác Nhận Đón", command=lambda: confirm_pickup(treeview, conn, audio_queue))
    confirm_btn.pack(side=tk.LEFT, padx=10)

    cancel_btn = tk.Button(button_frame, text="Hủy Đón", command=lambda: cancel_pickup(treeview, conn, audio_queue))
    cancel_btn.pack(side=tk.LEFT, padx=10)

    # Add "Phát Lại" button
    replay_btn = tk.Button(button_frame, text="Phát Lại", command=lambda: replay_pickup_selected(treeview, audio_queue, conn))
    replay_btn.pack(side=tk.LEFT, padx=10)

    # Refresh Button
    refresh_btn = tk.Button(button_frame, text="Làm Mới Danh Sách", command=lambda: refresh_pickup_list(conn, treeview, audio_queue))
    refresh_btn.pack(side=tk.LEFT, padx=10)

    # Timer Label
    timer_label = tk.Label(root, text="", font=('Helvetica', 12))
    timer_label.pack(pady=5)

    def update_timer():
        current_time = datetime.datetime.now().strftime("%I:%M:%S %p")
        current_date = datetime.datetime.now().strftime("%A, %B %d, %Y")
        timer_label.config(text=f"{current_date} {current_time}")
        root.after(1000, update_timer)

    update_timer()

    # Exit Button
    exit_button = tk.Button(root, text="Thoát", command=lambda: on_closing(root, stop_event))
    exit_button.pack(pady=10)

    return root, treeview, video_label

# --------------------------- Pickup Processing ---------------------------

def update_or_insert_pickup(treeview, pickup_data, audio_queue):
    """
    Update an existing pickup in the Treeview or insert a new one if it doesn't exist.
    """
    pickup_id = pickup_data['pickup_id']
    if treeview.exists(str(pickup_id)):
        # Update existing item
        treeview.item(str(pickup_id), values=(
            pickup_id,
            pickup_data['student_name'],
            pickup_data.get('class', 'Unknown'),
            pickup_data['created_at'],
            "Chờ xác nhận"
        ))
        logging.info(f"Pickup ID {pickup_id} đã tồn tại. Đã cập nhật thông tin.")
    else:
        # Insert new pickup
        treeview.insert("", "end", iid=str(pickup_id),
                        values=(
                            pickup_id,
                            pickup_data['student_name'],
                            pickup_data.get('class', 'Unknown'),
                            pickup_data['created_at'],
                            "Chờ xác nhận"
                        ))
        announcement_text = f"Xin mời học sinh {pickup_data['student_name']} lớp {pickup_data.get('class', 'Unknown')}, vui lòng ra cổng trường có phụ huynh đón."
        enqueue_audio(announcement_text, repeat=2)
        logging.info(f"Pickup ID {pickup_id} đã được thêm vào Treeview.")

def process_pickup_queue(pickup_queue, audio_queue, conn, treeview, stop_event):
    while not stop_event.is_set():
        try:
            pickup = pickup_queue.get(timeout=1)  # Wait for 1 second
        except queue.Empty:
            continue  # No pickup to process

        if pickup is None:
            break  # Sentinel to stop the thread

        pickup_id = pickup.get('pickup_id', 0)
        
        # Check if pickup_id has already been processed
        if pickup_id in processed_pickups:
            logging.warning(f"Pickup ID {pickup_id} đã được xử lý trước đó. Bỏ qua.")
            pickup_queue.task_done()
            continue

        name = pickup.get('student_name', 'Unknown')
        class_name = pickup.get('class', 'Unknown')  # Sử dụng .get() để tránh KeyError
        created_at = pickup.get('created_at', 'Unknown')

        # Prepare pickup_data
        pickup_data = {
            'pickup_id': pickup_id,
            'student_name': name,
            'class': class_name,
            'created_at': created_at
        }

        # Update or add pickup to Treeview
        update_or_insert_pickup(treeview, pickup_data, audio_queue)

        # Mark as processed
        processed_pickups.add(pickup_id)

        pickup_queue.task_done()

def polling_database(conn, pickup_queue, stop_event):
    """
    Continuously poll the database for new 'Chờ xử lý' pickups every 5 seconds.
    """
    last_id = 0
    while not stop_event.is_set():
        pickups = fetch_new_pickups(conn, last_id)
        if pickups:
            max_id = last_id
            for pickup in pickups:
                pickup_id, student_id, name, class_name, created_at = pickup
                if pickup_id in processed_pickups:
                    logging.info(f"Pickup ID {pickup_id} đã được xử lý. Bỏ qua.")
                    continue
                pickup_data = {
                    'pickup_id': pickup_id,
                    'student_id': student_id,
                    'student_name': name,
                    'class': class_name if class_name else 'Unknown',  # Đảm bảo không bị None
                    'created_at': created_at.strftime('%Y-%m-%d %H:%M:%S') if created_at else 'Unknown'
                }
                pickup_queue.put(pickup_data)
                if pickup_id > max_id:
                    max_id = pickup_id
            last_id = max_id  # Update last_id after processing all pickups
        time.sleep(5)  # Poll every 5 seconds

def refresh_pickup_list(conn, treeview, audio_queue):
    """
    Refresh the pickup list by fetching current 'Chờ xử lý' pickups from the database.
    """
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT p.id, s.id AS student_id, s.name, s.class, p.created_at
                FROM public.pickup_history p
                JOIN public.student s ON p.student_id = s.id
                WHERE p.status = 'Chờ xử lý'
                ORDER BY p.created_at DESC
            """)
            pickups = cur.fetchall()

            # Clear existing Treeview
            for item in treeview.get_children():
                treeview.delete(item)

            # Reset processed_pickups set
            processed_pickups.clear()

            # Add pickups to Treeview and enqueue audio
            for pickup in pickups:
                pickup_id, student_id, name, class_name, created_at = pickup
                pickup_data = {
                    'pickup_id': pickup_id,
                    'student_id': student_id,
                    'student_name': name,
                    'class': class_name if class_name else 'Unknown',  # Đảm bảo không bị None
                    'created_at': created_at.strftime('%Y-%m-%d %H:%M:%S') if created_at else 'Unknown'
                }
                update_or_insert_pickup(treeview, pickup_data, audio_queue)
                processed_pickups.add(pickup_id)
    except Exception as e:
        logging.error(f"Error refreshing pickup list: {e}")

# --------------------------- Confirm and Cancel Functions ---------------------------

def confirm_pickup(treeview, conn, audio_queue):
    """
    Confirm the selected pickup(s) and update the status to 'Đã đón'.
    """
    selected_items = treeview.selection()
    if not selected_items:
        messagebox.showwarning("Cảnh báo", "Vui lòng chọn ít nhất một mục để xác nhận.")
        return

    for item in selected_items:
        pickup_id = int(item)
        update_pickup_status(conn, pickup_id, 'Đã đón')
        treeview.item(item, values=(
            pickup_id,
            treeview.item(item, 'values')[1],
            treeview.item(item, 'values')[2],
            treeview.item(item, 'values')[3],
            "Đã đón"
        ))

    messagebox.showinfo("Thành công", "Các yêu cầu đã được xác nhận.")

def cancel_pickup(treeview, conn, audio_queue):
    """
    Cancel the selected pickup(s) and update the status to 'Đã hủy'.
    """
    selected_items = treeview.selection()
    if not selected_items:
        messagebox.showwarning("Cảnh báo", "Vui lòng chọn ít nhất một mục để hủy.")
        return

    for item in selected_items:
        pickup_id = int(item)
        update_pickup_status(conn, pickup_id, 'Đã hủy')
        treeview.item(item, values=(
            pickup_id,
            treeview.item(item, 'values')[1],
            treeview.item(item, 'values')[2],
            treeview.item(item, 'values')[3],
            "Đã hủy"
        ))

    messagebox.showinfo("Thành công", "Các yêu cầu đã bị hủy.")

# --------------------------- QR Code Scanning ---------------------------

def scan_qr_code(pickup_queue, frame_queue, stop_event):
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        logging.error("Error: Could not open camera.")
        return

    while not stop_event.is_set():
        ret, frame = cap.read()
        if not ret:
            logging.error("Failed to grab frame")
            break

        # Use pyzbar to decode QR code
        decoded_objects = pyzbar.decode(frame)

        for obj in decoded_objects:
            if obj.type == 'QRCODE':  # Check barcode type
                data = obj.data.decode('utf-8')
                try:
                    qr_data = json.loads(data)
                    # Expecting 'id' and 'name' in QR code
                    if "id" in qr_data and "name" in qr_data:
                        student_id = int(qr_data["id"])
                        student_name = qr_data["name"]

                        # Check if student_id is already in scanned_students
                        if student_id in scanned_students:
                            logging.warning(f"Học sinh {student_name} (ID: {student_id}) đã có yêu cầu đón đang chờ xử lý.")
                            enqueue_audio(f"Học sinh {student_name} đã có yêu cầu đón đang chờ xử lý.", repeat=1)
                            messagebox.showwarning("Cảnh báo", f"Học sinh {student_name} đã có yêu cầu đón đang chờ xử lý.")
                            continue  # Skip enqueueing

                        # Add student_id to scanned_students to prevent duplicate
                        scanned_students.add(student_id)

                        # Send POST request to Flask to enqueue pickup
                        try:
                            response = requests.post(
                                'http://localhost:5000/enqueue_pickup',
                                json={
                                    'student_id': student_id,
                                    'student_name': student_name
                                }
                            )
                            if response.status_code == 200:
                                response_data = response.json()
                                if response_data['status'] == 'success':
                                    logging.info(f"Pickup for {student_name} (ID: {student_id}) enqueued successfully.")
                                else:
                                    logging.error(f"Error from Flask /enqueue_pickup: {response_data['message']}")
                                    enqueue_audio(f"Học sinh {student_name} đã có yêu cầu đón đang chờ xử lý.", repeat=1)
                                    scanned_students.discard(student_id)
                            elif response.status_code == 409:
                                # Conflict: Student already has a pending pickup
                                response_data = response.json()
                                logging.error(f"Conflict from Flask /enqueue_pickup: {response_data['message']}")
                                enqueue_audio(f"Học sinh {student_name} đã có yêu cầu đón đang chờ xử lý.", repeat=1)
                                scanned_students.discard(student_id)
                            else:
                                logging.error(f"HTTP Error from Flask /enqueue_pickup: {response.status_code}, Content: {response.text}")
                                enqueue_audio(f"Lỗi khi gửi yêu cầu phát lại cho {student_name}.", repeat=1)
                                scanned_students.discard(student_id)
                        except Exception as e:
                            logging.error(f"Error sending request to Flask /enqueue_pickup: {e}")
                            enqueue_audio(f"Lỗi khi gửi yêu cầu phát lại cho {student_name}.", repeat=1)
                            scanned_students.discard(student_id)
                    else:
                        logging.error("Dữ liệu không hợp lệ, bỏ qua mục này.")
                except json.JSONDecodeError:
                    logging.error("Lỗi khi giải mã dữ liệu QR code")
            else:
                logging.info(f"Bỏ qua mã vạch loại: {obj.type}")

        # Đưa khung hình vào frame_queue để luồng chính cập nhật
        if not frame_queue.full():
            frame_queue.put(frame)

        # Exit on pressing 'q'
        if cv2.waitKey(1) & 0xFF == ord('q'):
            stop_event.set()
            break

    cap.release()
    cv2.destroyAllWindows()

def update_video_frame(video_label, frame_queue):
    if not frame_queue.empty():
        frame = frame_queue.get()

        # Chuyển đổi hình ảnh để hiển thị trên Tkinter
        cv2image = cv2.cvtColor(frame, cv2.COLOR_BGR2RGBA)
        img = Image.fromarray(cv2image)
        imgtk = ImageTk.PhotoImage(image=img)
        video_label.imgtk = imgtk
        video_label.configure(image=imgtk)

    # Tiếp tục gọi hàm này sau 10 mili giây
    video_label.after(10, update_video_frame, video_label, frame_queue)

# --------------------------- Replay Functions ---------------------------

def send_replay_request(student_id, student_name):
    try:
        # Send POST request to Flask /replay endpoint
        url = 'http://localhost:5000/replay'  # Ensure Flask is running at this address
        payload = {
            'student_id': student_id,
            'student_name': student_name
        }
        headers = {'Content-Type': 'application/json'}
        response = requests.post(url, headers=headers, json=payload)
        if response.status_code == 200:
            response_data = response.json()
            if response_data['status'] == 'success':
                logging.info(f"Phát lại thông báo cho {student_name} (ID: {student_id}) thành công.")
                enqueue_audio(f"Phát lại thông báo cho {student_name}.", repeat=1)
            else:
                logging.error(f"Lỗi khi phát lại: {response_data['message']}")
                enqueue_audio(f"Lỗi khi phát lại cho {student_name}: {response_data['message']}", repeat=1)
        elif response.status_code == 429:
            # Cooldown
            response_data = response.json()
            logging.warning(f"Cooldown from Flask /replay: {response_data['message']}")
            enqueue_audio(f"Học sinh {student_name} đã có yêu cầu đón đang chờ xử lý.", repeat=1)
        else:
            logging.error(f"Lỗi khi gửi yêu cầu: {response.status_code}, Nội dung: {response.text}")
            enqueue_audio(f"Lỗi khi gửi yêu cầu phát lại cho {student_name}.", repeat=1)
    except Exception as e:
        logging.error(f"Lỗi khi gửi yêu cầu phát lại: {e}")
        enqueue_audio(f"Lỗi khi gửi yêu cầu phát lại cho {student_name}.", repeat=1)

def replay_pickup_selected(treeview, audio_queue, conn):
    selected_items = treeview.selection()
    if not selected_items:
        messagebox.showwarning("Cảnh báo", "Vui lòng chọn ít nhất một mục để phát lại.")
        return

    for item in selected_items:
        pickup_id = int(item)
        try:
            # Fetch student_id and student_name from database based on pickup_id
            with conn.cursor() as cur:
                cur.execute("SELECT student_id FROM public.pickup_history WHERE id = %s", (pickup_id,))
                result = cur.fetchone()
                if result:
                    student_id = result[0]
                else:
                    logging.error(f"Không tìm thấy student_id cho pickup_id {pickup_id}")
                    enqueue_audio(f"Không tìm thấy thông tin cho pickup ID {pickup_id}.", repeat=1)
                    messagebox.showerror("Lỗi", f"Không tìm thấy thông tin cho pickup ID {pickup_id}.")
                    continue

            # Fetch student_name and class from database
            with conn.cursor() as cur:
                cur.execute("SELECT name, class FROM public.student WHERE id = %s", (student_id,))
                student = cur.fetchone()
                if student:
                    student_name = student[0]
                    student_class = student[1]
                else:
                    student_name = "Unknown"
                    student_class = "Unknown"

            # Send replay request to Flask API
            send_replay_request(student_id, student_name)
        except Exception as e:
            logging.error(f"Lỗi khi phát lại pickup ID {pickup_id}: {e}")
            enqueue_audio(f"Lỗi khi phát lại pickup ID {pickup_id}.", repeat=1)
            messagebox.showerror("Lỗi", f"Lỗi khi phát lại pickup ID {pickup_id}: {e}")

# --------------------------- Treeview Click Handler ---------------------------

def on_treeview_click(event, treeview, audio_queue, conn):
    region = treeview.identify("region", event.x, event.y)
    if region != "cell":
        return

    column = treeview.identify_column(event.x)
    row = treeview.identify_row(event.y)

    if not row:
        return

    # Get column index
    try:
        col_index = int(column.replace('#', '')) - 1
    except ValueError:
        return  # Invalid column

    # Assuming 'Trạng thái' is at column index 4
    if col_index == 4:  # Indexing starts at 0
        replay_audio(treeview, row, audio_queue, conn)

def replay_audio(treeview, pickup_id, audio_queue, conn):
    try:
        # Fetch student_id and student_name from database based on pickup_id
        with conn.cursor() as cur:
            cur.execute("SELECT student_id FROM public.pickup_history WHERE id = %s", (pickup_id,))
            result = cur.fetchone()
            if result:
                student_id = result[0]
            else:
                logging.error(f"Không tìm thấy student_id cho pickup_id {pickup_id}")
                enqueue_audio(f"Không tìm thấy thông tin cho pickup ID {pickup_id}.", repeat=1)
                messagebox.showerror("Lỗi", f"Không tìm thấy thông tin cho pickup ID {pickup_id}.")
                return

        # Fetch student_name from database
        with conn.cursor() as cur:
            cur.execute("SELECT name FROM public.student WHERE id = %s", (student_id,))
            student = cur.fetchone()
            if student:
                student_name = student[0]
            else:
                student_name = "Unknown"

        # Send replay request to Flask API
        send_replay_request(student_id, student_name)
    except Exception as e:
        logging.error(f"Lỗi khi phát lại âm thanh: {e}")
        enqueue_audio(f"Lỗi khi phát lại pickup ID {pickup_id}.", repeat=1)
        messagebox.showerror("Lỗi", f"Lỗi khi phát lại pickup ID {pickup_id}: {e}")

# --------------------------- Closing Handler ---------------------------

def on_closing(root, stop_event):
    """
    Handle closing of the application.
    """
    if messagebox.askokcancel("Thoát", "Bạn có chắc chắn muốn thoát?"):
        stop_event.set()
        root.destroy()

# --------------------------- Flask Routes ---------------------------

@app.route('/enqueue_pickup', methods=['POST'])
def enqueue_pickup():
    """
    Endpoint to enqueue pickup requests from PHP or QR scanner.
    Expects JSON data with 'student_id' and 'student_name'.
    """
    data = request.get_json()
    logging.info(f"Received data for pickup: {data}")  # Log dữ liệu nhận được

    if not data:
        logging.error("No JSON data received")
        return jsonify({'status': 'error', 'message': 'Không nhận được dữ liệu.'}), 400

    student_id = data.get('student_id')
    student_name = data.get('student_name')

    if not student_id or not student_name:
        logging.error("Missing required parameters.")
        return jsonify({'status': 'error', 'message': 'Thiếu thông tin học sinh.'}), 400

    # Truy xuất thông tin lớp và số điện thoại phụ huynh từ database dựa trên student_id
    conn = get_db_connection()
    if not conn:
        return jsonify({'status': 'error', 'message': 'Không thể kết nối đến database.'}), 500

    try:
        with conn.cursor() as cur:
            # Kiểm tra xem học sinh đã có yêu cầu đón 'Chờ xử lý' chưa
            cur.execute("""
                SELECT COUNT(*) FROM public.pickup_history
                WHERE student_id = %s AND status = 'Chờ xử lý'
            """, (student_id,))
            count = cur.fetchone()[0]
            if count > 0:
                return jsonify({'status': 'error', 'message': 'Học sinh này đã có yêu cầu đón đang chờ xử lý.'}), 409

            # Truy xuất thông tin lớp từ student_id
            cur.execute("""
                SELECT class, FPN, MPN FROM public.student WHERE id = %s
            """, (student_id,))
            result = cur.fetchone()
            if result:
                class_name, FPN, MPN = result
            else:
                return jsonify({'status': 'error', 'message': 'Không tìm thấy học sinh.'}), 404

            # Tìm kiếm user_id dựa trên FPN và MPN
            user_ids = []
            if FPN:
                cur.execute("""
                    SELECT id FROM public.user WHERE phone = %s
                """, (FPN,))
                users = cur.fetchall()
                user_ids.extend([user[0] for user in users])

            if MPN:
                cur.execute("""
                    SELECT id FROM public.user WHERE phone = %s
                """, (MPN,))
                users = cur.fetchall()
                user_ids.extend([user[0] for user in users])

            user_ids = list(set(user_ids))  # Loại bỏ trùng lặp

            pickup_ids = []
            pickup_details = []  # To store details including 'class'

            # Insert vào pickup_history và notifications
            for user_id in user_ids:
                # Insert vào pickup_history
                cur.execute("""
                    INSERT INTO public.pickup_history (student_id, user_id, status, created_at)
                    VALUES (%s, %s, 'Chờ xử lý', NOW())
                    RETURNING id
                """, (student_id, user_id))
                pickup_id = cur.fetchone()[0]
                pickup_ids.append(pickup_id)

                # Insert notification cho từng user
                notification_title = "Yêu Cầu Đón"
                notification_message = f"Yêu cầu đón học sinh {student_name} lớp {class_name} đã được thêm vào hệ thống."

                cur.execute("""
                    INSERT INTO public.notifications (user_id, title, message, status, created_at)
                    VALUES (%s, %s, %s, 'Chưa đọc', NOW())
                """, (user_id, notification_title, notification_message))

                # Append pickup detail
                pickup_details.append({
                    'pickup_id': pickup_id,
                    'student_name': student_name,
                    'class': class_name if class_name else 'Unknown',
                    'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                })

            # Nếu không tìm thấy user nào, thêm pickup_history với user_id là NULL
            if not user_ids:
                cur.execute("""
                    INSERT INTO public.pickup_history (student_id, user_id, status, created_at)
                    VALUES (%s, NULL, 'Chờ xử lý', NOW())
                    RETURNING id
                """, (student_id,))
                pickup_id = cur.fetchone()[0]
                pickup_ids.append(pickup_id)

                # Append pickup detail
                pickup_details.append({
                    'pickup_id': pickup_id,
                    'student_name': student_name,
                    'class': class_name if class_name else 'Unknown',
                    'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                })

        conn.commit()
    except Exception as e:
        logging.error(f"Lỗi trong Flask /enqueue_pickup endpoint: {e}")
        conn.rollback()
        return jsonify({'status': 'error', 'message': 'Lỗi server.'}), 500
    finally:
        conn.close()

    # Emit event to SocketIO clients to update pickup list
    try:
        # Emit detailed pickup information to avoid KeyError
        socketio.emit('new_pickup', {'pickups': pickup_details}, broadcast=True)
        logging.info("Emitted 'new_pickup' event to clients.")
    except Exception as e:
        logging.error(f"Lỗi khi emit 'new_pickup' event: {e}")
        return jsonify({'status': 'error', 'message': 'Lỗi khi emit sự kiện.'}), 500

    return jsonify({'status': 'success', 'message': 'Thông báo đã được thêm vào hàng đợi.', 'pickup_ids': pickup_ids}), 200

@app.route('/replay', methods=['POST'])
def replay():
    """
    Endpoint to enqueue replay requests.
    Expects JSON data with 'pickup_id', 'student_id', and 'student_name'.
    Implements a cooldown to prevent multiple replays within a short period.
    """
    data = request.get_json()
    logging.info(f"Received data for replay: {data}")  # Log received data

    if not data:
        logging.error("No JSON data received")
        return jsonify({'status': 'error', 'message': 'Không nhận được dữ liệu.'}), 400

    pickup_id = data.get('pickup_id')  # If required
    student_id = data.get('student_id')
    student_name = data.get('student_name')

    if not pickup_id or not student_id or not student_name:
        logging.error("Missing required parameters.")
        return jsonify({'status': 'error', 'message': 'Thiếu thông tin yêu cầu đón.'}), 400

    # Truy xuất thông tin lớp từ student_id
    conn = get_db_connection()
    if not conn:
        return jsonify({'status': 'error', 'message': 'Không thể kết nối đến database.'}), 500

    try:
        with conn.cursor() as cur:
            # Truy xuất thông tin lớp từ student_id
            cur.execute("""
                SELECT class FROM public.student WHERE id = %s
            """, (student_id,))
            result = cur.fetchone()
            if result:
                class_name = result[0]
            else:
                return jsonify({'status': 'error', 'message': 'Không tìm thấy học sinh.'}), 404
    except Exception as e:
        logging.error(f"Lỗi khi truy xuất thông tin lớp: {e}")
        return jsonify({'status': 'error', 'message': 'Lỗi server.'}), 500
    finally:
        conn.close()

    key = f"{student_id}"
    current_time = time.time()

    with replay_lock:
        last_time = last_replay_times.get(key, 0)
        if current_time - last_time < COOLDOWN_PERIOD:
            remaining = int(COOLDOWN_PERIOD - (current_time - last_time))
            return jsonify({'status': 'cooldown', 'message': f'Vui lòng chờ {remaining} giây trước khi phát lại.'}), 429

        # Update last_replay_time
        last_replay_times[key] = current_time

    announcement_text = f"Xin nhắc lại xin mời học sinh {student_name} lớp {class_name}, vui lòng ra cổng trường có phụ huynh đón."
    enqueue_audio(announcement_text, repeat=1)

    logging.info(f"Processed replay for student_id {student_id}, student_name {student_name}")
    return jsonify({'status': 'success', 'message': 'Phát lại thông báo đã được xử lý.'}), 200

@app.route('/shutdown', methods=['POST'])
def shutdown():
    """
    Endpoint to shutdown the server.
    """
    audio_queue.put(None)  # Place sentinel to stop thread
    func = request.environ.get('werkzeug.server.shutdown')
    if func is None:
        return jsonify({'status': 'error', 'message': 'Not running with the Werkzeug Server'}), 400
    func()
    return jsonify({'status': 'success', 'message': 'Đã tắt server'}), 200

# --------------------------- SocketIO Events ---------------------------

@socketio.on('connect')
def handle_connect():
    logging.info("Client connected")

@socketio.on('disconnect')
def handle_disconnect():
    logging.info("Client disconnected")

@socketio.on('new_pickup')
def handle_new_pickup(data):
    """
    Handle new_pickup events emitted from the server.
    """
    pickups = data.get('pickups', [])
    logging.info(f"Received 'new_pickup' event with {len(pickups)} pickups.")
    for pickup in pickups:
        pickup_id = pickup.get('pickup_id')
        if pickup_id in processed_pickups:
            logging.warning(f"Pickup ID {pickup_id} đã được xử lý trước đó. Bỏ qua.")
            continue
        # Add to pickup_queue for processing
        pickup_queue.put(pickup)

# --------------------------- Main Function ---------------------------

def main():
    # Connect to the database
    conn = get_db_connection()
    if not conn:
        logging.error("Failed to connect to the database. Exiting.")
        return

    # Create queues and events
    frame_queue = queue.Queue(maxsize=10)  # Queue để chứa khung hình
    stop_event = threading.Event()

    # Create GUI
    root, treeview, video_label = create_gui(pickup_queue, conn, stop_event)

    # Refresh pickup list on startup
    refresh_pickup_list(conn, treeview, audio_queue)

    # Start updating video frame
    update_video_frame(video_label, frame_queue)

    # Bind Treeview click event for 'Phát Lại' functionality
    treeview.bind("<Button-1>", lambda event: on_treeview_click(event, treeview, audio_queue, conn))

    # Start audio worker thread
    audio_thread = threading.Thread(target=audio_worker, args=(audio_queue, stop_event), daemon=True)
    audio_thread.start()

    # Start pickup processing thread
    processing_thread = threading.Thread(target=process_pickup_queue, args=(pickup_queue, audio_queue, conn, treeview, stop_event), daemon=True)
    processing_thread.start()

    # Start polling thread
    polling_thread = threading.Thread(target=polling_database, args=(conn, pickup_queue, stop_event), daemon=True)
    polling_thread.start()

    # Start QR code scanning thread
    qr_thread = threading.Thread(target=scan_qr_code, args=(pickup_queue, frame_queue, stop_event), daemon=True)
    qr_thread.start()

    # Start Flask server with SocketIO
    flask_thread = threading.Thread(target=lambda: socketio.run(app, host='0.0.0.0', port=5000, debug=False), daemon=True)
    flask_thread.start()

    # Run the GUI main loop
    root.mainloop()

    # Wait for threads to finish
    stop_event.set()
    processing_thread.join()
    polling_thread.join()
    qr_thread.join()
    audio_thread.join()
    # Flask server will shut down automatically when main thread exits

    # Close the database connection
    conn.close()

    # Quit pygame mixer
    pygame.mixer.quit()

if __name__ == "__main__":
    main()
