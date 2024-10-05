import json
import inflect
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
import json
# Connect to the PostgreSQL database
def connect_db():
    return psycopg2.connect(
        host="localhost",
        database="studentpickup",
        user="postgres",
        password="!xNq!TRWY.AuD9U"
    )

# Function to fetch pending pick-up requests from the database
def fetch_pending_requests():
    conn = connect_db()
    cursor = conn.cursor()
    cursor.execute("SELECT r.id, s.name, s.class FROM pickup_requests r JOIN student s ON r.student_id = s.id WHERE r.status = 'Pending'")
    results = cursor.fetchall()
    conn.close()
    return results

# Function to update the pick-up status to "Completed" when confirmed in Python
def update_pickup_status(request_id):
    conn = connect_db()
    cursor = conn.cursor()
    cursor.execute("UPDATE pickup_requests SET status = 'Completed' WHERE id = %s", (request_id,))
    conn.commit()
    conn.close()

# Function to import pick-ups into the waiting list
def import_pickups(treeview):
    while True:
        pending_requests = fetch_pending_requests()
        for request_id, student_name, student_class in pending_requests:
            # Insert the pending requests into your Treeview (or waiting list)
            treeview.insert("", "end", values=(request_id, student_name, student_class))

        time.sleep(10)  # Fetch every 10 seconds

# Function to handle pick-up confirmation in Python
def on_confirm_button_click(treeview, selected_item_id):
    # Assuming you're storing the pick-up request ID in the Treeview, you can fetch it:
    selected_item = treeview.item(selected_item_id)
    request_id = selected_item['values'][0]
    
    # Confirm the pick-up (this will update the status in the DB)
    update_pickup_status(request_id)

    # Remove the student from the Treeview
    treeview.delete(selected_item_id)

# Add this code into your GUI event loop so that it's continuously fetching and showing new requests
def start_gui():
    # Tkinter initialization and setup
    root = tk.Tk()
    treeview = ttk.Treeview(root, columns=("ID", "Name", "Class"), show="headings")
    
    # Start importing pending pick-ups in the background
    threading.Thread(target=import_pickups, args=(treeview,)).start()

    # Tkinter main loop
    root.mainloop()

if __name__ == "__main__":
    start_gui()


# Từ điển thay thế
replacement_dict = {
    "XH": "Xã Hội",
    "TN": "Tự Nhiên"
}

# Khởi tạo đối tượng inflect
p = inflect.engine()

def replace_abbreviations(text, replacement_dict):
    for key, value in replacement_dict.items():
        text = text.replace(key, value)
    return text

def convert_numbers_to_words(text):
    words = text.split()
    converted_words = []
    for word in words:
        if word.isdigit():
            converted_words.append(p.number_to_words(word, andword=""))
        else:
            converted_words.append(word)
    return ' '.join(converted_words)


# Hàm phát âm thanh
def play_audio(filename, event):
    try:
        pygame.mixer.music.load(filename)
        pygame.mixer.music.play()
        
        # Wait for the audio to finish playing
        while pygame.mixer.music.get_busy():
            pygame.time.Clock().tick(10)
    except Exception as e:
        print(f"Lỗi khi phát âm thanh: {e}")
    finally:
        pygame.mixer.music.stop()  # Dừng phát âm thanh
        pygame.mixer.music.unload()  # Giải phóng tệp âm thanh
        event.set()  # Đặt sự kiện khi phát xong âm thanh

# Hàm phát âm thanh thông báo
def play_sound(name, class_name):
    announcement = f"Xin mời học sinh {name} lớp {class_name}, vui lòng ra cổng trường có phụ huynh đón."
    print(f"Phát lại thông báo: {announcement}")
    tts = gTTS(text=announcement, lang='vi')
    sound_file = f"{name}_{class_name}.mp3"
    tts.save(sound_file)
    pygame.mixer.music.load(sound_file)
    pygame.mixer.music.play()
    while pygame.mixer.music.get_busy():
        time.sleep(1)
    pygame.mixer.music.unload()
    os.remove(sound_file)


def read_qr_code(qr_queue, video_label):
    cap = cv2.VideoCapture(0)
    detector = cv2.QRCodeDetector()
    while True:
        _, frame = cap.read()
        mask = cv2.rectangle(frame.copy(), (100, 100), (500, 500), (255, 255, 255), -1)
        blurred = cv2.GaussianBlur(frame, (21, 21), 0)
        frame = cv2.addWeighted(blurred, 1, mask, -1, 0)
        frame[100:500, 100:500] = frame[100:500, 100:500]
        
        data, bbox, _ = detector.detectAndDecode(frame[100:500, 100:500])
        if data:
            qr_queue.put(data)
            print(f"Đã quét được: {data}")
        
        # Chuyển đổi khung hình từ OpenCV sang định dạng mà Tkinter có thể hiển thị
        cv2image = cv2.cvtColor(frame, cv2.COLOR_BGR2RGBA)
        img = Image.fromarray(cv2image)
        imgtk = ImageTk.PhotoImage(image=img)
        video_label.imgtk = imgtk
        video_label.configure(image=imgtk)
        
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break
    cap.release()
    cv2.destroyAllWindows()

def update_video_label(video_label, cap):
    ret, frame = cap.read()
    if ret:
        # Chuyển đổi khung hình sang RGB
        frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        # Chuyển đổi khung hình sang ảnh PIL
        img = Image.fromarray(frame)
        # Chuyển đổi ảnh PIL sang ảnh ImageTk
        imgtk = ImageTk.PhotoImage(image=img)
        # Cập nhật video label với ảnh mới
        video_label.imgtk = imgtk
        video_label.configure(image=imgtk)
    # Gọi lại hàm này sau 10 ms
    video_label.after(10, update_video_label, video_label, cap)

qr_queue = queue.Queue()
is_reading = False
stop_event = threading.Event()
name_queue = queue.Queue()
counter = 1
scanned_names = set()
audio_queue = queue.Queue()
def scan_qr_code(cap, qr_queue, stop_event):
    detector = cv2.QRCodeDetector()
    while not stop_event.is_set():
        ret, frame = cap.read()
        if not ret:
            print("Failed to grab frame")
            break
        if ret:
            data, bbox, _ = detector.detectAndDecode(frame)
            if data:
                try:
                    qr_data = json.loads(data)
                    qr_queue.put(qr_data)
                    time.sleep(1)
                    if "Name" in qr_data and "Class" in qr_data:
                        qr_queue.put(qr_data)
                        print(f"QR Code detected: {qr_data}")
                        # Cập nhật giao diện Tkinter trong luồng chính
                        treeview.after(0, lambda: treeview.insert("", "end", values=(len(treeview.get_children()) + 1, qr_data["Name"], qr_data["Class"])))
                    else:
                        print("Dữ liệu không hợp lệ, bỏ qua mục này.")
                except json.JSONDecodeError:
                    print("Lỗi khi giải mã dữ liệu QR code")
        time.sleep(1)  # Thêm thời gian chờ để tránh vòng lặp quá nhanh


    cap.release()
    cv2.destroyAllWindows()



def process_queue(qr_queue, treeview, stop_event):
    global counter, scanned_names  # Sử dụng biến toàn cục
    while not stop_event.is_set():
        if not qr_queue.empty():
            data = qr_queue.get()
            try:
                # Giải mã dữ liệu JSON từ hàng đợi
                name = data["Name"]
                class_name = data["Class"]
                if name not in scanned_names:
                    announcement = f"Xin mời học sinh {name} lớp {class_name}, vui lòng ra cổng trường có phụ huynh đón."
                    print(f"Phát thông báo: {announcement}")
                    print("Đã phát thông báo.")
                    item_id = treeview.insert("", "end", values=(counter, name, class_name))
                    # Phát âm thanh thông báo
                    name_queue.put((counter, name, class_name))
                    scanned_names.add(name)  # Thêm tên vào tập hợp
                    counter += 1
                else:
                    print(f"Tên {name} đã được quét trước đó.")
            except KeyError as e:
                print(f"Lỗi khi truy cập dữ liệu JSON: {e}")
            time.sleep(2)
        else:
            time.sleep(0.5)    # Thêm thời gian chờ để tránh vòng lặp quá nhanh

# Hàm tạo tệp âm thanh
def create_audio_file(text, filename):
    tts = gTTS(text=text, lang='vi')
    tts.save(filename)

# Hàm đọc tên từ hàng đợi
def read_name_from_queue():
    global is_reading
    if not name_queue.empty() and not is_reading:
        is_reading = True
        counter, name, class_name = name_queue.get()
        # Giả lập đọc tên (ví dụ: sử dụng text-to-speech)
        print(f"Đọc tên: {counter} - {name} - {class_name}")
        
        # Sử dụng gTTS để chuyển đổi tên thành giọng nói
        announcement = f"Xin mời học sinh {name} lớp {class_name}, vui lòng ra cổng trường có phụ huynh đón."
        create_audio_file(announcement, "temp_audio.mp3")
        
        # Tạo sự kiện để đồng bộ hóa
        event = threading.Event()
        
        # Phát âm thanh khi đọc tên trong một luồng riêng biệt
        threading.Thread(target=play_audio, args=("temp_audio.mp3", event)).start()
        
        # Xóa tên khỏi treeview
        for item in treeview.get_children():
            if treeview.item(item, "values")[1] == name:
                treeview.delete(item)
                break
        
        # Đợi cho đến khi âm thanh phát xong
        event.wait(6)
        
        # Xóa tệp âm thanh sau khi phát xong và giải phóng
        try:
            os.remove("temp_audio.mp3")
        except PermissionError as e:
            print(f"Lỗi khi xóa tệp âm thanh: {e}")
        
        time.sleep(2)
        is_reading = False
        # Gọi lại hàm này để đọc tên tiếp theo
        root.after(100, read_name_from_queue)
    else:
        # Nếu hàng đợi trống hoặc đang đọc, gọi lại hàm này sau một khoảng thời gian ngắn
        root.after(100, read_name_from_queue)

def start_processing(qr_queue, treeview, stop_event):
    cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)  # Sử dụng backend CAP_DSHOW
    if not cap.isOpened():
        print("Error: Could not open camera.")
        return

    threading.Thread(target=process_queue, args=(qr_queue, treeview, stop_event)).start()
    threading.Thread(target=scan_qr_code, args=(cap, qr_queue, stop_event)).start()



def on_closing():
    print("Window is closing")
    stop_event.set() 
    root.destroy()

# Tạo giao diện Tkinter
root = tk.Tk()
root.title("QR Code Scanner")


# Tạo Notebook để chứa các tab
notebook = ttk.Notebook(root)
notebook.pack(pady=20, expand=True)

# Tạo khung cho tab quét mã QR
scan_frame = tk.Frame(notebook)
notebook.add(scan_frame, text="Quét mã QR")

# Tạo khung cho tab danh sách chờ
queue_frame = tk.Frame(notebook)
notebook.add(queue_frame, text="Danh sách chờ")

# Tạo Listbox để hiển thị danh sách chờ
columns = ("STT", "Tên", "Lớp", "Phát Lại", "Xác Nhận")
treeview = ttk.Treeview(queue_frame, columns=columns, show="headings")
treeview.heading("STT", text="STT")
treeview.heading("Tên", text="Tên")
treeview.heading("Lớp", text="Lớp")
treeview.heading("Phát Lại", text="Phát Lại")
treeview.heading("Xác Nhận", text="Xác Nhận")
treeview.column("STT", width=100, anchor='center')  
treeview.column("Tên", width=300, anchor='w')      
treeview.column("Lớp", width=200, anchor='center')
treeview.column("Phát Lại", width=100, anchor='center')
treeview.column("Xác Nhận", width=150, anchor='center')
# Đặt Treeview vào cửa sổ chính
treeview.pack(fill=tk.BOTH, expand=True)



video_label = tk.Label(scan_frame)
video_label.pack()
cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
update_video_label(video_label, cap)


start_processing(qr_queue, treeview, stop_event)


def on_confirm_button_click(item_id ,student_name):
    response = messagebox.askyesno("Xác nhận", f"Bạn có chắc chắn muốn xác nhận đón học sinh {student_name}?")
    if response:
        messagebox.showinfo("Xác nhận", f"Bạn đã xác nhận đón học sinh {student_name}")
        treeview.delete(item_id)
        items = treeview.get_children()
        for index, item in enumerate(items):
            values = list(treeview.item(item, 'values'))
            values[0] = str(index + 1)  # Cập nhật STT
            treeview.item(item, values=values)
    else:
        messagebox.showerror("Hủy bỏ", f"Bạn đã hủy xác nhận đón học sinh {student_name}")


timer_label = tk.Label(root, text="00:00:00 - YYYY-MM-DD" , anchor='w', font=('Helvetica', 12))
timer_label.pack(fill='x', padx=10, pady=10)
# Hàm cập nhật thời gian
def update_timer():
    current_time = time.strftime("%I:%M:%S %p") 
    current_date = time.strftime("%A, %B %d, %Y") 
    timer_label.config(text=f"{current_date} {current_time}")
    root.after(1000, update_timer)

# Bắt đầu cập nhật thời gian
update_timer()


def on_treeview_click(event):
    item = treeview.identify('item', event.x, event.y)
    column = treeview.identify_column(event.x)
    if column == '#4':  # Cột "Sound"
        values = treeview.item(item, 'values')
        if len(values) >= 3:  # Kiểm tra độ dài của values trước khi truy cập
            play_sound(values[1], values[2])
    elif column == '#5':  # Cột "Xác nhận"
        values = treeview.item(item, 'values')
        if len(values) >= 2:  # Kiểm tra độ dài của values trước khi truy cập
            on_confirm_button_click(item, values[1])

# Gắn sự kiện nhấn vào treeview
treeview.bind("<Button-1>", on_treeview_click)




# Thêm nút thoát
exit_button = tk.Button(root, text="Thoát", command=on_closing)
exit_button.pack(pady=10)

# Gắn hàm on_closing vào sự kiện đóng cửa sổ
root.protocol("WM_DELETE_WINDOW", on_closing)

root.after(100, read_name_from_queue)

# Chạy vòng lặp chính của Tkinter
root.mainloop()

stop_event.set()
cap.release()
pygame.mixer.quit()