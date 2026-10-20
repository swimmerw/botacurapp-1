import sys
p=sys.argv[1] if len(sys.argv)>1 else 'app/Http/Controllers/GiftCardController.php'
s=open(p,encoding='utf-8').read()
if 'function pdfDe(' in s:
    print('controlador: ya estaba'); sys.exit(0)
a=s.find('    public function enviarpdf($id)'); b=s.find('Mail::to($gc->correo)', a)
if a<0 or b<0: sys.exit('ERROR: no encontre enviarpdf')
nuevo='''    /** PDF de la gift card (diseño "Valle", A4 apaisado sin márgenes). */
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
s=s[:a]+nuevo+s[b:]
open(p,'w',encoding='utf-8').write(s); print('controlador: OK')
