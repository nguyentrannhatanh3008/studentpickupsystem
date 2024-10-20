from pyzbar import pyzbar
import cv2

def test_pyzbar():
    # Mở camera
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        print("Không thể mở camera.")
        return
    
    print("Đang chờ QR code... (Nhấn 'q' để thoát)")
    
    while True:
        ret, frame = cap.read()
        if not ret:
            print("Không thể lấy khung hình từ camera.")
            break
        
        # Giải mã QR code trong khung hình
        decoded_objects = pyzbar.decode(frame)
        
        for obj in decoded_objects:
            if obj.type == 'QRCODE':
                data = obj.data.decode('utf-8')
                print(f"Đã phát hiện QR code: {data}")
        
        # Hiển thị khung hình
        cv2.imshow('Test Pyzbar - Press q to Quit', frame)
        
        # Thoát khi nhấn 'q'
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break
    
    cap.release()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    test_pyzbar()
