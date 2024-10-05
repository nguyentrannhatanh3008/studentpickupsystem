import pandas as pd
import qrcode
import json
import os

# Từ điển thay thế viết tắt
replacement_dict = {
    "TN": "Tự Nhiên",
    "XH": "Xã Hội"
}

def replace_abbreviations(text, replacement_dict):
    for key, value in replacement_dict.items():
        text = text.replace(key, value)
    return text

# Đọc dữ liệu từ tệp Excel
df = pd.read_excel("C:\\Users\\PC DELL\\OneDrive\\Documents\\data.xlsx")

# Tạo thư mục lưu mã QR nếu chưa tồn tại
output_dir = "qr_codes"
if not os.path.exists(output_dir):
    os.makedirs(output_dir)

for index, row in df.iterrows():
    # Tạo dữ liệu theo định dạng JSON
    data = {
        "Name": replace_abbreviations(row['Name'], replacement_dict),
        "Class": replace_abbreviations(row['Class'], replacement_dict)
    }
    
    # Chuyển đổi dữ liệu sang chuỗi JSON
    data_str = json.dumps(data)
    
    # Tạo mã QR
    qr = qrcode.QRCode(
        version=1,  
        error_correction=qrcode.constants.ERROR_CORRECT_L,  
        box_size=10, 
        border=4
    )
    qr.add_data(data_str)
    qr.make(fit=True)
    
    # Tạo ảnh mã QR
    img = qr.make_image(fill='black', back_color='white')
    
    # Lưu ảnh mã QR với tên file theo định dạng JSON
    file_name = f"{output_dir}/{data['Name']}.png"
    img.save(file_name)