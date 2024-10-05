from flask import Flask, request, jsonify
import threading
from gtts import gTTS
import os
import pygame

replacement_dict = {
    "XH": "Xã Hội",
    "TN": "Tự Nhiên"
}
app = Flask(__name__)

pygame.mixer.init()

# Function to play sound
def play_sound(name, class_name):
    announcement = f"Xin mời học sinh {name} lớp {class_name}, vui lòng ra cổng trường có phụ huynh đón."
    tts = gTTS(text=announcement, lang='vi')
    sound_file = f"{name}_{class_name}.mp3"
    tts.save(sound_file)
    pygame.mixer.music.load(sound_file)
    pygame.mixer.music.play()
    while pygame.mixer.music.get_busy():
        pass
    pygame.mixer.music.unload()
    os.remove(sound_file)

# Flask route to handle pickup registration (accepting POST requests)
@app.route('/register_pickup', methods=['POST'])
def register_pickup():
    data = request.json
    student_name = data.get('student_name')
    class_name = data.get('class_name')

    if not student_name or not class_name:
        return jsonify({"error": "Missing student_name or class_name"}), 400

    # Process the pick-up and play sound (asynchronously)
    threading.Thread(target=play_sound, args=(student_name, class_name)).start()

    return jsonify({'message': f'Pick-up registered for {student_name}, class {class_name}'}), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
