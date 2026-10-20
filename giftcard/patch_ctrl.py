import sys
p = sys.argv[1] if len(sys.argv) > 1 else 'app/Http/Controllers/GiftCardController.php'
s = open(p, encoding='utf-8').read()
cambios = []

# 1) PDF en una sola funcion (pdfDe), vista previa (verpdf) y enviarpdf usandola
if 'function pdfDe(' not in s:
    a = s.find('    public function enviarpdf($id)')
    b = s.find('Mail::to($gc->correo)', a)
    if a < 0 or b < 0:
        sys.exit('ERROR: no encontre enviarpdf')
    nuevo = '''    /** PDF de la gift card (diseño "Valle", A4 apaisado sin márgenes). */
    private function pdfDe(GiftCard $gc)
    {
        $generator = new BarcodeGeneratorPNG();
        $barcode   = base64_encode($generator->getBarcode($gc->codigo, $generator::TYPE_CODE_128, 2, 60));

        return sPDF::loadView('pdf.giftcard.viewPDF', [
            'gc'       => $gc,
            'programa' => $gc->programa,
            'barcode'  => $barcode,
        ])->setOption('enable-local-file-access', true)
            ->setOption('disable-smart-shrinking', true)
            ->setOption('margin-top', 0)->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0)->setOption('margin-right', 0)
            ->setPaper('a4')
            ->setOrientation('landscape');
    }

    /** Ver el PDF en el navegador antes de enviarlo. */
    public function verpdf($id)
    {
        $gc = GiftCard::with('programa.servicios')->findOrFail($id);
        return $this->pdfDe($gc)->inline('GiftCard-' . $gc->codigo . '.pdf');
    }

    public function enviarpdf($id)
    {
        $gc = GiftCard::with('programa.servicios')->findOrFail($id);
        $pdfData = $this->pdfDe($gc)->output();

        '''
    s = s[:a] + nuevo + s[b:]
    cambios.append('PDF y vista previa')

# 2) Escala segun el wkhtmltopdf del servidor (sin "patched qt" encoge todo y deja borde blanco)
if 'function zoomPdf(' not in s:
    k = "            'barcode'  => $barcode,\n        ])"
    if k not in s:
        sys.exit('ERROR: no encontre la llamada a la vista del PDF')
    s = s.replace(k, "            'barcode'  => $barcode,\n            'zoom'     => self::zoomPdf(),\n        ])", 1)
    a = s.find('    /** PDF de la gift card')
    metodo = '''    /**
     * wkhtmltopdf sin "patched qt" ignora disable-smart-shrinking y achica la página a ~77 %:
     * se compensa con zoom para que el diseño llene la hoja completa.
     */
    private static function zoomPdf(): float
    {
        static $zoom = null;
        if ($zoom === null) {
            $bin = config('snappy.pdf.binary');
            $ver = function_exists('exec') ? @exec(escapeshellarg($bin) . ' --version 2>&1') : '';
            $zoom = (stripos((string) $ver, 'patched qt') !== false) ? 1.0 : 1.3;
        }
        return $zoom;
    }

'''
    s = s[:a] + metodo + s[a:]
    cambios.append('escala de la hoja')

open(p, 'w', encoding='utf-8').write(s)
print('controlador: ' + (', '.join(cambios) if cambios else 'ya estaba'))
