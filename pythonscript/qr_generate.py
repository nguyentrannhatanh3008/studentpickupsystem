import psycopg2
import qrcode
import io
import json

# Cấu hình cơ sở dữ liệu
DB_CONFIG = {
    'host': 'localhost',
    'database': 'studentpickup',
    'user': 'postgres',
    'password': '!xNq!TRWY.AuD9U'  # Thay bằng mật khẩu thực tế của bạn
}

def get_students(conn):
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM public.student")
        return cur.fetchall()

def generate_qr_code(student_id, student_name):
    # Dữ liệu để mã QR
    data = json.dumps({
        "id": student_id,
        "name": student_name
    }, ensure_ascii=False)
    
    # Tạo mã QR
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    qr.add_data(data)
    qr.make(fit=True)
    
    img = qr.make_image(fill_color="black", back_color="white")
    
    # Lưu hình ảnh mã QR vào bytes
    byte_arr = io.BytesIO()
    img.save(byte_arr, format='PNG')
    return byte_arr.getvalue()

def insert_qr_code(conn, student_id, qr_image):
    with conn.cursor() as cur:
        # Kiểm tra xem mã QR đã tồn tại chưa
        cur.execute("SELECT id FROM public.student_qrcodes WHERE student_id = %s", (student_id,))
        result = cur.fetchone()
        if result:
            # Cập nhật mã QR đã tồn tại
            cur.execute("""
                UPDATE public.student_qrcodes 
                SET qr_image = %s, created_at = CURRENT_TIMESTAMP 
                WHERE student_id = %s
            """, (psycopg2.Binary(qr_image), student_id))
        else:
            # Chèn mã QR mới
            cur.execute("""
                INSERT INTO public.student_qrcodes (student_id, qr_image) 
                VALUES (%s, %s)
            """, (student_id, psycopg2.Binary(qr_image)))
    conn.commit()

def main():
    # Kết nối đến cơ sở dữ liệu
    conn = psycopg2.connect(**DB_CONFIG)
    
    try:
        students = get_students(conn)
        for student in students:
            student_id, student_name = student
            qr_image = generate_qr_code(student_id, student_name)
            insert_qr_code(conn, student_id, qr_image)
            print(f"QR code đã được tạo và lưu cho học sinh ID: {student_id}, Tên: {student_name}")
    except Exception as e:
        print(f"Lỗi: {e}")
    finally:
        conn.close()

if __name__ == "__main__":
    main()
