<?php
class PropertyController extends Controller
{
    private function access(): array
    {
        Auth::requireRole('superadmin', 'admin', 'vendedor');
        return Auth::user();
    }

    private function existing(array $user): array
    {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $property = $id ? Property::find($id) : null;
        if (!$property) {
            http_response_code(404);
            exit('Propiedad no encontrada.');
        }
        if ($user['rol'] === 'vendedor' && (int)$property['creado_por'] !== $user['id']) {
            http_response_code(403);
            exit('No tienes permiso para editar esta propiedad.');
        }
        return $property;
    }

    public function index(): void
    {
        $user = $this->access();
        $filters=[];
        foreach (['q','modalidad_id','moneda','min','max','disponibilidad','publicado'] as $key) $filters[$key]=is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        $filterErrors=[]; $properties=[];
        try {
            $filters=Property::validateFilters($_GET);
            $properties=Property::listing($user,$filters['q'],$filters);
        } catch (DomainException $error) { http_response_code(422); $filterErrors[]=$error->getMessage(); }
        $this->view('properties/index', ['title'=>'Propiedades','properties'=>$properties,'query'=>$filters['q'],'filters'=>$filters,'filterErrors'=>$filterErrors,'modes'=>Property::catalogs()['modes'],'success'=>$_SESSION['property_success'] ?? null,'error'=>$_SESSION['property_error'] ?? null]);
        unset($_SESSION['property_success'], $_SESSION['property_error']);
    }

    public function create(): void
    {
        $this->access();
        $this->form(['moneda' => 'PEN', 'disponibilidad' => 'disponible']);
    }

    public function edit(): void
    {
        $this->form($this->existing($this->access()));
    }

    private function form(array $property, array $errors = []): void
    {
        $this->view('properties/form', ['title' => isset($property['id']) ? 'Editar propiedad' : 'Nueva propiedad', 'property' => $property, 'errors' => $errors, 'photos' => isset($property['id']) ? Property::photos((int)$property['id']) : []] + Property::catalogs());
    }

    public function delete(): void
    {
        $user = $this->access();
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) {
            http_response_code(403);
            exit('Sesión expirada. Recarga la página.');
        }
        $property = $this->existing($user);
        try { Property::delete((int)$property['id']); }
        catch (DomainException $error) { $_SESSION['property_error']=$error->getMessage(); $this->redirect('/properties'); }
        $_SESSION['property_success'] = 'Propiedad eliminada del listado.';
        $this->redirect('/properties');
    }

    public function save(): void
    {
        $user = $this->access();
        if (!Csrf::verify(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) {
            http_response_code(403);
            exit('Sesión expirada. Recarga la página.');
        }
        $existing = isset($_GET['id']) ? $this->existing($user) : null;
        $data = [];
        foreach (['titulo','descripcion','tipo_id','modalidad_id','precio','moneda','area_m2','dormitorios','banos','disponibilidad','departamento','provincia','distrito','direccion'] as $key) {
            $data[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        }
        $data['publicado'] = isset($_POST['publicado']) ? 1 : 0;
        $errors = [];
        $hasLocation = $data['departamento'] !== '' || $data['provincia'] !== '' || $data['distrito'] !== '' || $data['direccion'] !== '';
        foreach (['departamento','provincia','distrito','direccion'] as $key) {
            if (mb_strlen($data[$key]) > ($key === 'direccion' ? 200 : 60)) $errors[] = 'La ubicación supera la longitud permitida.';
            if ($hasLocation && $key !== 'direccion' && $data[$key] === '') $errors[] = 'Para guardar la ubicación completa departamento, provincia y distrito.';
        }
        if ($data['titulo'] === '' || mb_strlen($data['titulo']) > 160) $errors[] = 'El título es obligatorio y admite hasta 160 caracteres.';
        if (strlen($data['descripcion']) > 60000) $errors[] = 'La descripción es demasiado extensa.';
        $catalogs = Property::catalogs();
        foreach (['tipo_id' => 'types', 'modalidad_id' => 'modes'] as $field => $catalog) {
            if (!in_array($data[$field], array_map('strval', array_column($catalogs[$catalog], 'id')), true)) $errors[] = 'Selecciona un tipo y una modalidad válidos.';
        }
        if (!preg_match('/^[0-9]{1,7}$/D', $data['precio']) || (int)$data['precio'] < 1 || (int)$data['precio'] > 5000000) $errors[] = 'El precio debe ser un número entero entre 1 y 5 000 000, tanto en soles como en dólares.';
        if (!in_array($data['moneda'], ['PEN','USD'], true)) $errors[] = 'Selecciona una moneda válida.';
        if (!in_array($data['disponibilidad'], ['disponible','reservado','vendido','alquilado'], true)) $errors[] = 'Selecciona una disponibilidad válida.';
        if ($data['area_m2'] !== '' && (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $data['area_m2']) || (float)$data['area_m2'] <= 0)) $errors[] = 'El área debe ser mayor que cero con hasta dos decimales.';
        foreach (['dormitorios','banos'] as $key) {
            if ($data[$key] !== '' && (!ctype_digit($data[$key]) || (int)$data[$key] > 255)) $errors[] = 'Dormitorios y baños deben ser enteros entre 0 y 255.';
        }
        if ($errors) {
            http_response_code(422);
            $this->form($data + ($existing ? ['id' => $existing['id']] : []), $errors);
            return;
        }
        foreach (['area_m2','dormitorios','banos'] as $key) if ($data[$key] === '') $data[$key] = null;
        $uploads = [];
        try {
            $uploads = PropertyPhotos::receive();
            $remove = is_array($_POST['remove_photos'] ?? null) ? array_map(fn($id) => is_scalar($id) ? (int)$id : 0, $_POST['remove_photos']) : [];
            $primary = is_scalar($_POST['primary_photo'] ?? null) ? (int)$_POST['primary_photo'] : 0;
            Property::save($data, $existing ? (int)$existing['id'] : null, $user['id'], $uploads, $remove, $primary);
        } catch (Throwable $error) {
            PropertyPhotos::discard($uploads);
            $reference=ErrorHandler::report($error,'PropertyController.save');
            $validation=$error instanceof DomainException || ($error instanceof RuntimeException && !($error instanceof PDOException));
            http_response_code($validation ? 422 : 503);
            $this->form($data + ($existing ? ['id' => $existing['id']] : []), [$validation ? $error->getMessage() : 'No se pudieron guardar los cambios. Referencia: '.$reference]);
            return;
        }
        $_SESSION['property_success'] = $existing ? 'Propiedad actualizada.' : 'Propiedad registrada.';
        $this->redirect('/properties');
    }
}
