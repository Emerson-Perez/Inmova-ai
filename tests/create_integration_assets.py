from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4
from reportlab.lib.colors import HexColor, white
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

ROOT = Path(__file__).resolve().parents[1]
photo = ROOT / 'docs/integration/imagen-demo.png'
pdf = ROOT / 'output/pdf/ficha-inmueble-demo.pdf'
photo.parent.mkdir(parents=True, exist_ok=True)
pdf.parent.mkdir(parents=True, exist_ok=True)
font_file = 'C:/Windows/Fonts/arial.ttf'
font_bold = 'C:/Windows/Fonts/arialbd.ttf'
font = ImageFont.truetype(font_file, 27)
heading = ImageFont.truetype(font_bold, 42)
small = ImageFont.truetype(font_file, 22)
im = Image.new('RGB', (1200, 800), '#edf3f6')
d = ImageDraw.Draw(im)
d.rectangle((0, 0, 1200, 136), fill='#173f50')
d.text((55, 30), 'INMOVA | INMUEBLE DEMO', font=heading, fill='white')
d.text((55, 91), 'Departamento de prueba - Miraflores, Lima', font=small, fill='#d6e4e9')
rooms = [
    ((60, 185, 540, 445), 'Sala / comedor', '#d8e8ec'),
    ((560, 185, 840, 445), 'Dormitorio 1', '#c2dce3'),
    ((860, 185, 1140, 445), 'Dormitorio 2', '#c2dce3'),
    ((60, 465, 370, 680), 'Cocina', '#e5ddd0'),
    ((390, 465, 700, 680), 'Dormitorio 3', '#c2dce3'),
    ((720, 465, 1140, 680), '2 baños / circulación', '#dae2e8'),
]
for rect, label, color in rooms:
    d.rectangle(rect, fill=color, outline='#173f50', width=5)
    box = d.textbbox((0, 0), label, font=font)
    x = (rect[0] + rect[2] - (box[2] - box[0])) / 2
    y = (rect[1] + rect[3] - (box[3] - box[1])) / 2
    d.text((x, y), label, font=font, fill='#173f50')
d.text((60, 728), '95.50 m² | PEN 350 000 | IMAGEN SINTÉTICA DE DEMOSTRACIÓN', font=small, fill='#52636e')
im.save(photo)

pdfmetrics.registerFont(TTFont('DemoArial', font_file))
pdfmetrics.registerFont(TTFont('DemoArialBold', font_bold))
c = canvas.Canvas(str(pdf), pagesize=A4)
c.setTitle('Ficha de inmueble DEMO - prueba de integración')
c.setAuthor('INMOVA - datos ficticios')
w, h = A4
c.setFillColor(HexColor('#173f50')); c.rect(0, h-115, w, 115, fill=1, stroke=0)
c.setFillColor(white); c.setFont('DemoArialBold', 22); c.drawString(42, h-51, 'Ficha de inmueble DEMO')
c.setFont('DemoArial', 11); c.drawString(42, h-78, 'Caso ficticio para recorrer el flujo comercial de INMOVA')
c.setFillColor(HexColor('#173f50')); c.setFont('DemoArialBold', 17)
c.drawString(42, h-155, 'Departamento en Miraflores')
c.setFont('DemoArial', 11)
rows = [
    ('Operación', 'Venta de departamento'), ('Precio publicado', 'PEN 350 000'),
    ('Área', '95.50 m²'), ('Distribución', '3 dormitorios y 2 baños'),
    ('Ubicación', 'Lima / Lima / Miraflores'), ('Dirección ficticia', 'Av. Demostración 123'),
]
y = h-191
for label, value in rows:
    c.setFont('DemoArialBold', 10); c.drawString(42, y, label)
    c.setFont('DemoArial', 11); c.drawString(197, y, value)
    c.setStrokeColor(HexColor('#e1e8ed')); c.line(42, y-10, w-42, y-10); y -= 34
c.drawImage(str(photo), (w-450)/2, 145, width=450, height=300)
c.setFont('DemoArial', 10); c.setFillColor(HexColor('#52636e'))
c.drawString(42, 111, 'Documento de prueba: los datos y el esquema son ficticios.')
c.drawString(42, 92, 'No representa un inmueble ni una operación real.')
c.setStrokeColor(HexColor('#d8e1e6')); c.line(42, 61, w-42, 61)
c.setFont('DemoArial', 9); c.drawString(42, 43, 'INMOVA | Integración de propiedades, documentos, CRM y cierre')
c.drawRightString(w-42, 43, '1 / 1')
c.save()
print('Activos de demostración creados.')
