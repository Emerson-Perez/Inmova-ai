<?php
class PortalController extends Controller
{
    public function index(): void
    {
        $filters = [];
        foreach (['q','tipo_id','modalidad_id','moneda','min','max'] as $key) $filters[$key] = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        $errors = [];
        if (mb_strlen($filters['q']) > 160) $errors[] = 'La búsqueda admite hasta 160 caracteres.';
        foreach (['tipo_id','modalidad_id'] as $key) if ($filters[$key] !== '' && !preg_match('/^[1-9][0-9]{0,2}$/D', $filters[$key])) $errors[] = 'Selecciona un tipo y una modalidad válidos.';
        if (!in_array($filters['moneda'], ['','PEN','USD'], true)) $errors[] = 'Selecciona una moneda válida.';
        foreach (['min','max'] as $key) if ($filters[$key] !== '' && (!preg_match('/^[0-9]{1,7}$/D', $filters[$key]) || (int)$filters[$key] > 5000000)) $errors[] = 'Los precios deben ser enteros entre 0 y 5 000 000.';
        if (($filters['min'] !== '' || $filters['max'] !== '') && $filters['moneda'] === '') $errors[] = 'Selecciona una moneda para filtrar por precio.';
        if ($filters['min'] !== '' && $filters['max'] !== '' && (int)$filters['min'] > (int)$filters['max']) $errors[] = 'El precio mínimo no puede superar al máximo.';
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range'=>1]]) ?: 1;
        $results = $errors ? ['properties'=>[], 'total'=>0,'page'=>1,'pages'=>1] : PublicProperty::search($filters, $page);
        if ($errors) http_response_code(422);
        $this->view('portal/index', ['title'=>'Encuentra tu próximo inmueble', 'filters'=>$filters, 'errors'=>$errors] + $results + Property::catalogs());
    }

    public function show(): void
    {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $property = $id ? PublicProperty::find($id) : null;
        if (!$property) {
            http_response_code(404);
            $this->view('portal/not-found', ['title'=>'Inmueble no disponible']);
            return;
        }
        $this->view('portal/show', ['title'=>$property['titulo'], 'property'=>$property, 'photos'=>Property::photos((int)$property['id'])]);
    }
}
