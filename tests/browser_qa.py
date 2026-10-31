"""QA de navegador con cuentas y datos DEMO propios; no llama al proveedor IA.
Ejecutar con el Python que tenga Playwright y Chrome instalados.
Conserva registros comerciales y desactiva sus cuentas al finalizar.
"""
import json
import secrets
import subprocess
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tmp/qa-python'))
from playwright.sync_api import sync_playwright, expect

BASE = 'http://localhost/inmova-ai/public'
RUN = 'demo_' + datetime.now(timezone.utc).strftime('%Y%m%d_%H%M%S_') + secrets.token_hex(3)
OUT = ROOT / 'output/browser-qa' / RUN
OUT.mkdir(parents=True)
CHECKS, ERRORS = [], []
PASSWORD = 'Qa1!' + secrets.token_hex(16)

def fixture(action, **extra):
    result = subprocess.run(['C:/xampp/php/php.exe', str(ROOT/'tests/integration_fixture.php'), action],
                            input=json.dumps(dict(run=RUN, **extra)), capture_output=True, text=True, encoding='utf-8')
    if result.returncode:
        raise RuntimeError('Fallo en fixture: ' + action)
    return json.loads(result.stdout)

def check(label, condition=True):
    CHECKS.append(dict(prueba=label, correcta=bool(condition)))
    print(('OK: ' if condition else 'FAIL: ') + label, flush=True)
    if not condition:
        raise AssertionError(label)

def goto(page, path, status=200):
    response = page.goto(BASE + path)
    expect(page.locator('body')).to_be_visible()
    assert response.status == status, (path, response.status)
    assert 'Fatal error' not in page.locator('body').inner_text()

def submit(page, name):
    with page.expect_navigation():
        page.get_by_role('button', name=name, exact=True).click()

def login(page, email):
    goto(page, '/login')
    page.locator('#email').fill(email)
    page.locator('#password').fill(PASSWORD)
    submit(page, 'Ingresar a mi cuenta')

def shot(page, name):
    # Solo páginas con registros propios o formularios sin datos del usuario.
    page.screenshot(path=str(OUT/(name+'.png')), full_page=True)

created = False
failure = None
try:
    metadata = fixture('bootstrap', password=PASSWORD)
    created = True
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, executable_path='C:/Program Files/Google/Chrome/Application/chrome.exe')
        try:
            def newpage():
                context = browser.new_context(viewport=dict(width=1440, height=1000), locale='es-PE')
                page = context.new_page()
                page.on('pageerror', lambda error: ERRORS.append(str(error)))
                page.on('dialog', lambda dialog: dialog.accept())
                return page
            admin, seller, visitor, outsider = [newpage() for _ in range(4)]
            goto(visitor, '/dashboard')
            check('Visitante es dirigido al login', '/login' in visitor.url)
            visitor.locator('#email').fill('qa-inexistente@example.test')
            visitor.locator('#password').fill(PASSWORD)
            submit(visitor, 'Ingresar a mi cuenta')
            expect(visitor.get_by_text('Correo o contraseña incorrectos.', exact=True)).to_be_visible()
            check('Credenciales inválidas muestran un error')
            login(admin, metadata['admin_email'])
            check('Administrador ingresa al dashboard', '/dashboard' in admin.url)
            for prefix, page in [('vendedora', seller), ('externo', outsider)]:
                goto(admin, '/users/create')
                for field, value in dict(nombres='QA Navegador', apellidos=prefix, email=f'{prefix}.{RUN}@inmova.test', password=PASSWORD, password_confirmation=PASSWORD).items():
                    admin.locator('#'+field).fill(value)
                admin.locator('#rol_id').select_option(str(metadata['roles']['vendedor']))
                submit(admin, 'Guardar usuario')
                login(page, f'{prefix}.{RUN}@inmova.test')
                check('Cuenta '+prefix+' exige cambio de contraseña', '/account/password' in page.url)
                page.locator('[name=current_password]').fill(PASSWORD)
                page.locator('[name=new_password]').fill(PASSWORD+'X')
                page.locator('[name=password_confirmation]').fill(PASSWORD+'X')
                with page.expect_navigation():
                    page.get_by_role('button', name='Actualizar contraseña', exact=True).click()
            seller_id = next(u['id'] for u in fixture('snapshot')['users'] if u['email'].startswith('vendedora.'))
            goto(seller, '/users', 403)
            check('Vendedor no puede administrar usuarios')
            goto(seller, '/properties/create')
            title = f'DEMO {RUN} | Venta completada Miraflores'
            for field, value in dict(titulo=title, precio='350000', descripcion='Datos ficticios de QA de navegador.', departamento='Lima', provincia='Lima', distrito='Santa Anita', direccion='Calle QA DEMO 123').items():
                seller.locator('#'+field).fill(value)
            seller.locator('#tipo_id').select_option(str(metadata['types']['Departamento']))
            seller.locator('#modalidad_id').select_option(str(metadata['modes']['venta-deptos']))
            seller.locator('#publicado').check()
            seller.locator('#photos').set_input_files(str(ROOT/'docs/integration/imagen-demo.png'))
            submit(seller, 'Guardar propiedad')
            prop = fixture('snapshot')['properties'][0]['id']
            check('Propiedad con fotografía creada desde formulario', prop > 0)
            seller.locator('#q').fill(RUN)
            seller.locator('#modalidad_id').select_option(str(metadata['modes']['venta-deptos']))
            seller.locator('#moneda').select_option('PEN')
            seller.locator('#min').fill('350000')
            seller.locator('#max').fill('350000')
            seller.locator('#disponibilidad').select_option('disponible')
            seller.locator('#publicado').select_option('1')
            submit(seller, 'Buscar propiedades')
            expect(seller.locator('tbody tr')).to_have_count(1)
            expect(seller.locator('tbody th')).to_have_text('1')
            check('Filtros combinados y numeración de propiedades')
            shot(seller, '01-filtros-escritorio')
            seller.set_viewport_size(dict(width=390, height=844))
            shot(seller, '02-filtros-movil')
            mobile_ok=seller.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
            CHECKS.append(dict(prueba='Filtros móviles sin desbordamiento de página', correcta=mobile_ok))
            if not mobile_ok:
                overflow=seller.locator('body *').evaluate_all('(els)=>els.filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,25).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width}))')
                (OUT/'mobile-overflow.json').write_text(json.dumps(overflow,indent=2),encoding='utf-8')
            print(('OK: ' if mobile_ok else 'FAIL: ')+'Filtros móviles sin desbordamiento de página',flush=True)
            seller.locator('#nav-toggle').click()
            expect(seller.locator('#nav-toggle')).to_have_attribute('aria-expanded','true')
            expect(seller.locator('#app-sidebar')).to_be_visible()
            seller.locator('#nav-close').click()
            expect(seller.locator('#nav-toggle')).to_have_attribute('aria-expanded','false')
            check('Menú móvil abre y cierra con sus controles')
            seller.locator('.table-responsive').scroll_into_view_if_needed()
            seller.locator('.table-responsive').evaluate('(e)=>e.scrollLeft=e.scrollWidth')
            expect(seller.get_by_role('link',name='Editar',exact=True)).to_be_in_viewport()
            check('Tabla móvil permite desplazar hasta sus acciones')
            seller.set_viewport_size(dict(width=1440, height=1000))
            goto(visitor, f'/portal/property?id={prop}')
            expect(visitor.get_by_role('heading', name=title, exact=True)).to_be_visible()
            expect(visitor.locator('img').first).to_be_visible()
            for field, value in dict(nombres='Cliente QA navegador', email=f'ana.{RUN}@example.test', telefono='000000001', mensaje='DEMO: solicito visitar este departamento en Santa Anita.').items():
                visitor.locator('#'+field).fill(value)
            visitor.locator('[name=consent]').check()
            with visitor.expect_navigation():
                visitor.locator('form[action*="/portal/inquiry"] button').click()
            expect(visitor.get_by_role('status')).to_contain_text('Recibimos tu consulta')
            lead = fixture('snapshot')['leads'][0]['id']
            check('Consulta pública crea contacto y CRM')
            goto(admin, f'/crm/lead?id={lead}')
            admin.locator('#vendedor_id').select_option(str(seller_id))
            admin.locator('#etapa').select_option('contactado')
            admin.locator('#detalle').fill('QA: cliente contactado y asignado.')
            submit(admin, 'Guardar cambios')
            goto(seller, f'/crm/lead?id={lead}')
            expect(seller.get_by_role('heading', name='Historial de seguimiento')).to_be_visible()
            check('CRM asigna vendedor y conserva seguimiento')
            goto(outsider, f'/crm/lead?id={lead}', 403)
            check('Otro vendedor no accede a consulta ajena')
            seller.get_by_role('link', name='Programar visita', exact=True).click()
            expect(seller.locator('#fecha_hora')).to_be_visible()
            at = datetime.now(timezone(timedelta(hours=-5))) + timedelta(minutes=1)
            seller.locator('#fecha_hora').fill(at.strftime('%Y-%m-%dT%H:%M'))
            seller.locator('#estado_visita').select_option('confirmada')
            seller.locator('#resultado').fill('QA: visita confirmada con cliente ficticio.')
            submit(seller, 'Guardar visita')
            visit = fixture('snapshot')['visits'][0]['id']
            check('Visita confirmada desde navegador')
            goto(seller, f'/visits/edit?id={visit}')
            seller.locator('#estado_visita').select_option('realizada')
            seller.locator('#resultado').fill('QA: intento anticipado.')
            submit(seller, 'Guardar visita')
            expect(seller.get_by_role('alert')).to_be_visible()
            check('Visita futura no puede marcarse realizada')
            # Espera acotada a la hora real; no altera el reloj ni datos históricos.
            while datetime.now(timezone(timedelta(hours=-5))) < at.replace(second=0, microsecond=0):
                seller.wait_for_timeout(500)
            seller.locator('#resultado').fill('QA: visita realizada, distribución revisada.')
            submit(seller, 'Guardar visita')
            check('Visita realizada guarda resultado', fixture('snapshot')['visits'][0]['estado_visita']=='realizada')
            goto(seller, f'/crm/lead?id={lead}')
            seller.get_by_role('link', name='Solicitar reserva', exact=True).click()
            seller.locator('#monto_reserva').fill('5000')
            submit(seller, 'Solicitar reserva')
            reservation = fixture('snapshot')['reservations'][0]['id']
            check('Vendedor solicita reserva pendiente')
            goto(seller, f'/reservations/show?id={reservation}')
            check('Vendedor no tiene opción de aprobar', seller.locator('#accion option[value=aprobada]').count()==0)
            goto(admin, f'/reservations/show?id={reservation}')
            admin.locator('#accion').select_option('aprobada')
            admin.locator('#motivo').fill('QA: aprobación del administrador, datos ficticios.')
            submit(admin, 'Aplicar cambio')
            snapshot=fixture('snapshot')
            check('Aprobación sincroniza reserva, propiedad y CRM', snapshot['reservations'][0]['estado_reserva']=='aprobada' and snapshot['properties'][0]['disponibilidad']=='reservado' and snapshot['leads'][0]['etapa']=='reservado')
            admin.get_by_role('link', name='Registrar cierre de operación', exact=True).click()
            admin.locator('#monto_final').fill('350000')
            admin.locator('#observaciones').fill('QA DEMO: cierre ficticio completo desde Chrome.')
            submit(admin, 'Confirmar cierre')
            snapshot=fixture('snapshot')
            check('Cierre sincroniza venta, reserva y consulta', snapshot['properties'][0]['disponibilidad']=='vendido' and snapshot['properties'][0]['publicado']==0 and snapshot['reservations'][0]['estado_reserva']=='cerrada' and snapshot['leads'][0]['etapa']=='cerrado')
            shot(admin, '03-cierre')
            goto(visitor, f'/portal/property?id={prop}',404)
            check('Inmueble vendido deja de estar disponible en portal')
            goto(seller, f'/crm/lead?id={lead}')
            check('Consulta cerrada no ofrece visitas ni reservas', seller.get_by_role('link',name='Programar visita',exact=True).count()==0 and seller.get_by_role('link',name='Solicitar reserva',exact=True).count()==0)
            shot(seller, '04-historial-crm')
            for route in ['/dashboard','/properties','/crm','/opportunities','/visits','/reservations','/closings','/reports','/tasks','/academy','/simulations','/assistant']:
                goto(seller, route)
                expect(seller.locator('h1').first).to_be_visible()
                check('Navegación de vendedor '+route)
            # Cierre real de sesión mediante su formulario renderizado.
            seller.locator('.account-menu summary').click()
            with seller.expect_navigation():
                seller.locator('form[action$="/logout"] button').click()
            goto(seller, '/dashboard')
            check('Cerrar sesión impide volver al dashboard','/login' in seller.url)
            check('Sin excepciones JavaScript durante el recorrido', not ERRORS)
        finally:
            browser.close()
except Exception as error:
    failure = type(error).__name__ + ': ' + str(error)
    print('ERROR: '+failure, flush=True)
finally:
    if created:
        snapshot=fixture('snapshot')
        if snapshot['properties'] and not snapshot['leads']:
            fixture('hide_incomplete_properties')
        fixture('secure_accounts')
    (OUT/'resultado.json').write_text(json.dumps(dict(run=RUN, checks=CHECKS, javascript_errors=ERRORS, failure=failure, accounts_disabled=created),ensure_ascii=False,indent=2),encoding='utf-8')
    print('EVIDENCIA: '+str(OUT),flush=True)
sys.exit(1 if failure or any(not c['correcta'] for c in CHECKS) else 0)
