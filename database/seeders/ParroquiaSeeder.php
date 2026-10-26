<?php

namespace Database\Seeders;

use App\Models\Admin\Ubicaciones\Canton;
use App\Models\Admin\Ubicaciones\Parroquia;
use Illuminate\Database\Seeder;

class ParroquiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cantones = Canton::pluck('id', 'codigo_inec');

        // Fuentes: Codigos DPA 2026 y Clasificador Geografico Estadistico 2026 (INEC).
        // El codigo 50 representa la cabecera cantonal; los codigos 51-89 son rurales.
        // https://aplicaciones2.ecuadorencifras.gob.ec/SIN/descargas/cdpa2026.xlsx
        // https://aplicaciones2.ecuadorencifras.gob.ec/SIN/descargas/cge2026.pdf
        $parroquias = [
            // CUENCA - AZUAY
            ['codigo_inec' => '010150', 'canton' => '0101', 'nombre' => 'CUENCA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010151', 'canton' => '0101', 'nombre' => 'BAÑOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010152', 'canton' => '0101', 'nombre' => 'CUMBE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010153', 'canton' => '0101', 'nombre' => 'CHAUCHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010154', 'canton' => '0101', 'nombre' => 'CHECA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010155', 'canton' => '0101', 'nombre' => 'CHIQUINTAD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010156', 'canton' => '0101', 'nombre' => 'LLACAO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010157', 'canton' => '0101', 'nombre' => 'MOLLETURO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010158', 'canton' => '0101', 'nombre' => 'NULTI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010159', 'canton' => '0101', 'nombre' => 'OCTAVIO CORDERO PALACIOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010160', 'canton' => '0101', 'nombre' => 'PACCHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010161', 'canton' => '0101', 'nombre' => 'QUINGEO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010162', 'canton' => '0101', 'nombre' => 'RICAURTE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010163', 'canton' => '0101', 'nombre' => 'SAN JOAQUÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010164', 'canton' => '0101', 'nombre' => 'SANTA ANA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010165', 'canton' => '0101', 'nombre' => 'SAYAUSÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010166', 'canton' => '0101', 'nombre' => 'SIDCAY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010167', 'canton' => '0101', 'nombre' => 'SININCAY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010168', 'canton' => '0101', 'nombre' => 'TARQUI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010169', 'canton' => '0101', 'nombre' => 'TURI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010170', 'canton' => '0101', 'nombre' => 'VALLE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010171', 'canton' => '0101', 'nombre' => 'VICTORIA DEL PORTETE', 'tipo' => 'RURAL'],

            // GIRÓN - AZUAY
            ['codigo_inec' => '010250', 'canton' => '0102', 'nombre' => 'GIRÓN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010251', 'canton' => '0102', 'nombre' => 'LA ASUNCIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010252', 'canton' => '0102', 'nombre' => 'SAN GERARDO', 'tipo' => 'RURAL'],

            // GUALACEO - AZUAY
            ['codigo_inec' => '010350', 'canton' => '0103', 'nombre' => 'GUALACEO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010352', 'canton' => '0103', 'nombre' => 'DANIEL CÓRDOVA TORAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010353', 'canton' => '0103', 'nombre' => 'JADÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010354', 'canton' => '0103', 'nombre' => 'MARIANO MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010356', 'canton' => '0103', 'nombre' => 'REMIGIO CRESPO TORAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010357', 'canton' => '0103', 'nombre' => 'SAN JUAN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010358', 'canton' => '0103', 'nombre' => 'ZHIDMAD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010359', 'canton' => '0103', 'nombre' => 'LUIS CORDERO VEGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010360', 'canton' => '0103', 'nombre' => 'SIMÓN BOLÍVAR', 'tipo' => 'RURAL'],

            // NABÓN - AZUAY
            ['codigo_inec' => '010450', 'canton' => '0104', 'nombre' => 'NABÓN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010451', 'canton' => '0104', 'nombre' => 'COCHAPATA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010452', 'canton' => '0104', 'nombre' => 'EL PROGRESO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010453', 'canton' => '0104', 'nombre' => 'LAS NIEVES', 'tipo' => 'RURAL'],

            // PAUTE - AZUAY
            ['codigo_inec' => '010550', 'canton' => '0105', 'nombre' => 'PAUTE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010552', 'canton' => '0105', 'nombre' => 'BULÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010553', 'canton' => '0105', 'nombre' => 'CHICÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010554', 'canton' => '0105', 'nombre' => 'EL CABO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010556', 'canton' => '0105', 'nombre' => 'GUARAINAG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010559', 'canton' => '0105', 'nombre' => 'SAN CRISTÓBAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010561', 'canton' => '0105', 'nombre' => 'TOMEBAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010562', 'canton' => '0105', 'nombre' => 'DUG DUG', 'tipo' => 'RURAL'],

            // PUCARÁ - AZUAY
            ['codigo_inec' => '010650', 'canton' => '0106', 'nombre' => 'PUCARÁ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010652', 'canton' => '0106', 'nombre' => 'SAN RAFAEL DE SHARUG', 'tipo' => 'RURAL'],

            // SAN FERNANDO - AZUAY
            ['codigo_inec' => '010750', 'canton' => '0107', 'nombre' => 'SAN FERNANDO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010751', 'canton' => '0107', 'nombre' => 'CHUMBLÍN', 'tipo' => 'RURAL'],

            // SANTA ISABEL - AZUAY
            ['codigo_inec' => '010850', 'canton' => '0108', 'nombre' => 'SANTA ISABEL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010851', 'canton' => '0108', 'nombre' => 'ABDÓN CALDERÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010852', 'canton' => '0108', 'nombre' => 'EL CARMEN DE PIJILÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010853', 'canton' => '0108', 'nombre' => 'SHAGLLI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010854', 'canton' => '0108', 'nombre' => 'SAN SALVADOR DE CAÑARIBAMBA', 'tipo' => 'RURAL'],

            // SÍGSIG - AZUAY
            ['codigo_inec' => '010950', 'canton' => '0109', 'nombre' => 'SÍGSIG', 'tipo' => 'URBANA'],
            ['codigo_inec' => '010951', 'canton' => '0109', 'nombre' => 'CUCHIL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010952', 'canton' => '0109', 'nombre' => 'JIMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010953', 'canton' => '0109', 'nombre' => 'GÜEL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010954', 'canton' => '0109', 'nombre' => 'LUDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010955', 'canton' => '0109', 'nombre' => 'SAN BARTOLOMÉ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '010956', 'canton' => '0109', 'nombre' => 'SAN JOSÉ DE RARANGA', 'tipo' => 'RURAL'],

            // OÑA - AZUAY
            ['codigo_inec' => '011050', 'canton' => '0110', 'nombre' => 'SAN FELIPE DE OÑA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '011051', 'canton' => '0110', 'nombre' => 'SUSUDEL', 'tipo' => 'RURAL'],

            // CHORDELEG - AZUAY
            ['codigo_inec' => '011150', 'canton' => '0111', 'nombre' => 'CHORDELEG', 'tipo' => 'URBANA'],
            ['codigo_inec' => '011151', 'canton' => '0111', 'nombre' => 'PRINCIPAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '011152', 'canton' => '0111', 'nombre' => 'LA UNIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '011153', 'canton' => '0111', 'nombre' => 'LUIS GALARZA ORELLANA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '011154', 'canton' => '0111', 'nombre' => 'SAN MARTÍN DE PUZHIO', 'tipo' => 'RURAL'],

            // EL PAN - AZUAY
            ['codigo_inec' => '011250', 'canton' => '0112', 'nombre' => 'EL PAN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '011253', 'canton' => '0112', 'nombre' => 'SAN VICENTE', 'tipo' => 'RURAL'],

            // SEVILLA DE ORO - AZUAY
            ['codigo_inec' => '011350', 'canton' => '0113', 'nombre' => 'SEVILLA DE ORO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '011351', 'canton' => '0113', 'nombre' => 'AMALUZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '011352', 'canton' => '0113', 'nombre' => 'PALMAS', 'tipo' => 'RURAL'],

            // GUACHAPALA - AZUAY
            ['codigo_inec' => '011450', 'canton' => '0114', 'nombre' => 'GUACHAPALA', 'tipo' => 'URBANA'],

            // CAMILO PONCE ENRÍQUEZ - AZUAY
            ['codigo_inec' => '011550', 'canton' => '0115', 'nombre' => 'CAMILO PONCE ENRÍQUEZ', 'tipo' => 'URBANA'],

            // GUARANDA - BOLÍVAR
            ['codigo_inec' => '020150', 'canton' => '0201', 'nombre' => 'GUARANDA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '020151', 'canton' => '0201', 'nombre' => 'FACUNDO VELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020153', 'canton' => '0201', 'nombre' => 'JULIO E. MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020155', 'canton' => '0201', 'nombre' => 'SALINAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020156', 'canton' => '0201', 'nombre' => 'SAN LORENZO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020157', 'canton' => '0201', 'nombre' => 'SAN SIMÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020158', 'canton' => '0201', 'nombre' => 'SANTA FE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020159', 'canton' => '0201', 'nombre' => 'SIMIÁTUG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020160', 'canton' => '0201', 'nombre' => 'SAN LUIS DE PAMBIL', 'tipo' => 'RURAL'],

            // CHILLANES - BOLÍVAR
            ['codigo_inec' => '020250', 'canton' => '0202', 'nombre' => 'CHILLANES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '020251', 'canton' => '0202', 'nombre' => 'SAN JOSÉ DEL TAMBO', 'tipo' => 'RURAL'],

            // CHIMBO - BOLÍVAR
            ['codigo_inec' => '020350', 'canton' => '0203', 'nombre' => 'SAN JOSÉ DE CHIMBO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '020351', 'canton' => '0203', 'nombre' => 'ASUNCIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020353', 'canton' => '0203', 'nombre' => 'LA MAGDALENA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020354', 'canton' => '0203', 'nombre' => 'SAN SEBASTIÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020355', 'canton' => '0203', 'nombre' => 'TELIMBELA', 'tipo' => 'RURAL'],

            // ECHEANDÍA - BOLÍVAR
            ['codigo_inec' => '020450', 'canton' => '0204', 'nombre' => 'ECHEANDÍA', 'tipo' => 'URBANA'],

            // SAN MIGUEL - BOLÍVAR
            ['codigo_inec' => '020550', 'canton' => '0205', 'nombre' => 'SAN MIGUEL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '020551', 'canton' => '0205', 'nombre' => 'BALSAPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020552', 'canton' => '0205', 'nombre' => 'BILOVÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020553', 'canton' => '0205', 'nombre' => 'RÉGULO DE MORA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020554', 'canton' => '0205', 'nombre' => 'SAN PABLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020555', 'canton' => '0205', 'nombre' => 'SANTIAGO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '020556', 'canton' => '0205', 'nombre' => 'SAN VICENTE', 'tipo' => 'RURAL'],

            // CALUMA - BOLÍVAR
            ['codigo_inec' => '020650', 'canton' => '0206', 'nombre' => 'CALUMA', 'tipo' => 'URBANA'],

            // LAS NAVES - BOLÍVAR
            ['codigo_inec' => '020750', 'canton' => '0207', 'nombre' => 'LAS NAVES', 'tipo' => 'URBANA'],

            // AZOGUES - CAÑAR
            ['codigo_inec' => '030150', 'canton' => '0301', 'nombre' => 'AZOGUES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '030151', 'canton' => '0301', 'nombre' => 'COJITAMBO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030153', 'canton' => '0301', 'nombre' => 'GUAPÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030154', 'canton' => '0301', 'nombre' => 'JAVIER LOYOLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030155', 'canton' => '0301', 'nombre' => 'LUIS CORDERO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030156', 'canton' => '0301', 'nombre' => 'PINDILIG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030157', 'canton' => '0301', 'nombre' => 'RIVERA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030158', 'canton' => '0301', 'nombre' => 'SAN MIGUEL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030160', 'canton' => '0301', 'nombre' => 'TADAY', 'tipo' => 'RURAL'],

            // BIBLIÁN - CAÑAR
            ['codigo_inec' => '030250', 'canton' => '0302', 'nombre' => 'BIBLIÁN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '030251', 'canton' => '0302', 'nombre' => 'NAZÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030252', 'canton' => '0302', 'nombre' => 'SAN FRANCISCO DE SAGEO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030253', 'canton' => '0302', 'nombre' => 'TURUPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030254', 'canton' => '0302', 'nombre' => 'JERUSALÉN', 'tipo' => 'RURAL'],

            // CAÑAR - CAÑAR
            ['codigo_inec' => '030350', 'canton' => '0303', 'nombre' => 'CAÑAR', 'tipo' => 'URBANA'],
            ['codigo_inec' => '030351', 'canton' => '0303', 'nombre' => 'CHONTAMARCA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030352', 'canton' => '0303', 'nombre' => 'CHOROCOPTE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030353', 'canton' => '0303', 'nombre' => 'GENERAL MORALES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030354', 'canton' => '0303', 'nombre' => 'GUALLETURO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030355', 'canton' => '0303', 'nombre' => 'HONORATO VÁSQUEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030356', 'canton' => '0303', 'nombre' => 'INGAPIRCA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030357', 'canton' => '0303', 'nombre' => 'JUNCAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030358', 'canton' => '0303', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030361', 'canton' => '0303', 'nombre' => 'ZHUD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030362', 'canton' => '0303', 'nombre' => 'VENTURA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030363', 'canton' => '0303', 'nombre' => 'DUCUR', 'tipo' => 'RURAL'],

            // LA TRONCAL - CAÑAR
            ['codigo_inec' => '030450', 'canton' => '0304', 'nombre' => 'LA TRONCAL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '030451', 'canton' => '0304', 'nombre' => 'MANUEL J. CALLE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '030452', 'canton' => '0304', 'nombre' => 'PANCHO NEGRO', 'tipo' => 'RURAL'],

            // EL TAMBO - CAÑAR
            ['codigo_inec' => '030550', 'canton' => '0305', 'nombre' => 'EL TAMBO', 'tipo' => 'URBANA'],

            // DÉLEG - CAÑAR
            ['codigo_inec' => '030650', 'canton' => '0306', 'nombre' => 'DÉLEG', 'tipo' => 'URBANA'],
            ['codigo_inec' => '030651', 'canton' => '0306', 'nombre' => 'SOLANO', 'tipo' => 'RURAL'],

            // SUSCAL - CAÑAR
            ['codigo_inec' => '030750', 'canton' => '0307', 'nombre' => 'SUSCAL', 'tipo' => 'URBANA'],

            // TULCÁN - CARCHI
            ['codigo_inec' => '040150', 'canton' => '0401', 'nombre' => 'TULCÁN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '040151', 'canton' => '0401', 'nombre' => 'EL CARMELO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040153', 'canton' => '0401', 'nombre' => 'JULIO ANDRADE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040154', 'canton' => '0401', 'nombre' => 'MALDONADO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040155', 'canton' => '0401', 'nombre' => 'PIOTER', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040156', 'canton' => '0401', 'nombre' => 'TOBAR DONOSO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040157', 'canton' => '0401', 'nombre' => 'TUFIÑO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040158', 'canton' => '0401', 'nombre' => 'URBINA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040159', 'canton' => '0401', 'nombre' => 'EL CHICAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040161', 'canton' => '0401', 'nombre' => 'SANTA MARTHA DE CUBA', 'tipo' => 'RURAL'],

            // BOLÍVAR - CARCHI
            ['codigo_inec' => '040250', 'canton' => '0402', 'nombre' => 'BOLÍVAR', 'tipo' => 'URBANA'],
            ['codigo_inec' => '040251', 'canton' => '0402', 'nombre' => 'GARCÍA MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040252', 'canton' => '0402', 'nombre' => 'LOS ANDES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040253', 'canton' => '0402', 'nombre' => 'MONTE OLIVO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040254', 'canton' => '0402', 'nombre' => 'SAN VICENTE DE PUSIR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040255', 'canton' => '0402', 'nombre' => 'SAN RAFAEL', 'tipo' => 'RURAL'],

            // ESPEJO - CARCHI
            ['codigo_inec' => '040350', 'canton' => '0403', 'nombre' => 'EL ÁNGEL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '040351', 'canton' => '0403', 'nombre' => 'EL GOALTAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040352', 'canton' => '0403', 'nombre' => 'LA LIBERTAD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040353', 'canton' => '0403', 'nombre' => 'SAN ISIDRO', 'tipo' => 'RURAL'],

            // MIRA - CARCHI
            ['codigo_inec' => '040450', 'canton' => '0404', 'nombre' => 'MIRA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '040451', 'canton' => '0404', 'nombre' => 'CONCEPCIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040452', 'canton' => '0404', 'nombre' => 'JIJÓN Y CAAMAÑO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040453', 'canton' => '0404', 'nombre' => 'JUAN MONTALVO', 'tipo' => 'RURAL'],

            // MONTÚFAR - CARCHI
            ['codigo_inec' => '040550', 'canton' => '0405', 'nombre' => 'SAN GABRIEL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '040551', 'canton' => '0405', 'nombre' => 'CRISTÓBAL COLÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040552', 'canton' => '0405', 'nombre' => 'CHITÁN DE NAVARRETE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040553', 'canton' => '0405', 'nombre' => 'FERNÁNDEZ SALVADOR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040554', 'canton' => '0405', 'nombre' => 'LA PAZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '040555', 'canton' => '0405', 'nombre' => 'PIARTAL', 'tipo' => 'RURAL'],

            // SAN PEDRO DE HUACA - CARCHI
            ['codigo_inec' => '040650', 'canton' => '0406', 'nombre' => 'HUACA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '040651', 'canton' => '0406', 'nombre' => 'MARISCAL SUCRE', 'tipo' => 'RURAL'],

            // LATACUNGA - COTOPAXI
            ['codigo_inec' => '050150', 'canton' => '0501', 'nombre' => 'LATACUNGA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050151', 'canton' => '0501', 'nombre' => 'ALÁQUEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050152', 'canton' => '0501', 'nombre' => 'BELISARIO QUEVEDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050153', 'canton' => '0501', 'nombre' => 'GUAYTACAMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050154', 'canton' => '0501', 'nombre' => 'JOSEGUANGO BAJO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050156', 'canton' => '0501', 'nombre' => 'MULALÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050157', 'canton' => '0501', 'nombre' => 'ONCE DE NOVIEMBRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050158', 'canton' => '0501', 'nombre' => 'POALÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050159', 'canton' => '0501', 'nombre' => 'SAN JUAN DE PASTOCALLE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050161', 'canton' => '0501', 'nombre' => 'TANICUCHÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050162', 'canton' => '0501', 'nombre' => 'TOACASO', 'tipo' => 'RURAL'],

            // LA MANÁ - COTOPAXI
            ['codigo_inec' => '050250', 'canton' => '0502', 'nombre' => 'LA MANÁ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050251', 'canton' => '0502', 'nombre' => 'GUASAGANDA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050252', 'canton' => '0502', 'nombre' => 'PUCAYACU', 'tipo' => 'RURAL'],

            // PANGUA - COTOPAXI
            ['codigo_inec' => '050350', 'canton' => '0503', 'nombre' => 'EL CORAZÓN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050351', 'canton' => '0503', 'nombre' => 'MORASPUNGO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050352', 'canton' => '0503', 'nombre' => 'PINLLOPATA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050353', 'canton' => '0503', 'nombre' => 'RAMÓN CAMPAÑA', 'tipo' => 'RURAL'],

            // PUJILÍ - COTOPAXI
            ['codigo_inec' => '050450', 'canton' => '0504', 'nombre' => 'PUJILÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050451', 'canton' => '0504', 'nombre' => 'ANGAMARCA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050453', 'canton' => '0504', 'nombre' => 'GUANGAJE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050455', 'canton' => '0504', 'nombre' => 'LA VICTORIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050456', 'canton' => '0504', 'nombre' => 'PILALÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050457', 'canton' => '0504', 'nombre' => 'TINGO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050458', 'canton' => '0504', 'nombre' => 'ZUMBAHUA', 'tipo' => 'RURAL'],

            // SALCEDO - COTOPAXI
            ['codigo_inec' => '050550', 'canton' => '0505', 'nombre' => 'SAN MIGUEL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050551', 'canton' => '0505', 'nombre' => 'ANTONIO JOSÉ HOLGUÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050552', 'canton' => '0505', 'nombre' => 'CUSUBAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050553', 'canton' => '0505', 'nombre' => 'MULALILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050554', 'canton' => '0505', 'nombre' => 'MULLIQUINDIL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050555', 'canton' => '0505', 'nombre' => 'PANSALEO', 'tipo' => 'RURAL'],

            // SAQUISILÍ - COTOPAXI
            ['codigo_inec' => '050650', 'canton' => '0506', 'nombre' => 'SAQUISILÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050651', 'canton' => '0506', 'nombre' => 'CANCHAGUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050652', 'canton' => '0506', 'nombre' => 'CHANTILÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050653', 'canton' => '0506', 'nombre' => 'COCHAPAMBA', 'tipo' => 'RURAL'],

            // SIGCHOS - COTOPAXI
            ['codigo_inec' => '050750', 'canton' => '0507', 'nombre' => 'SIGCHOS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '050751', 'canton' => '0507', 'nombre' => 'CHUGCHILLÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050752', 'canton' => '0507', 'nombre' => 'ISINLIVI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050753', 'canton' => '0507', 'nombre' => 'LAS PAMPAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '050754', 'canton' => '0507', 'nombre' => 'PALO QUEMADO', 'tipo' => 'RURAL'],

            // RIOBAMBA - CHIMBORAZO
            ['codigo_inec' => '060150', 'canton' => '0601', 'nombre' => 'RIOBAMBA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060151', 'canton' => '0601', 'nombre' => 'CACHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060152', 'canton' => '0601', 'nombre' => 'CALPI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060153', 'canton' => '0601', 'nombre' => 'CUBIJÍES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060154', 'canton' => '0601', 'nombre' => 'FLORES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060155', 'canton' => '0601', 'nombre' => 'LICÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060156', 'canton' => '0601', 'nombre' => 'LICTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060157', 'canton' => '0601', 'nombre' => 'PUNGALÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060158', 'canton' => '0601', 'nombre' => 'PUNÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060159', 'canton' => '0601', 'nombre' => 'QUIMIAG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060160', 'canton' => '0601', 'nombre' => 'SAN JUAN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060161', 'canton' => '0601', 'nombre' => 'SAN LUIS', 'tipo' => 'RURAL'],

            // ALAUSÍ - CHIMBORAZO
            ['codigo_inec' => '060250', 'canton' => '0602', 'nombre' => 'ALAUSÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060251', 'canton' => '0602', 'nombre' => 'ACHUPALLAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060253', 'canton' => '0602', 'nombre' => 'GUASUNTOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060254', 'canton' => '0602', 'nombre' => 'HUIGRA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060255', 'canton' => '0602', 'nombre' => 'MULTITUD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060256', 'canton' => '0602', 'nombre' => 'PISTISHI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060257', 'canton' => '0602', 'nombre' => 'PUMALLACTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060258', 'canton' => '0602', 'nombre' => 'SEVILLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060259', 'canton' => '0602', 'nombre' => 'SIBAMBE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060260', 'canton' => '0602', 'nombre' => 'TIXÁN', 'tipo' => 'RURAL'],

            // COLTA - CHIMBORAZO
            ['codigo_inec' => '060350', 'canton' => '0603', 'nombre' => 'VILLA LA UNIÓN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060351', 'canton' => '0603', 'nombre' => 'CAÑI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060352', 'canton' => '0603', 'nombre' => 'COLUMBE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060353', 'canton' => '0603', 'nombre' => 'JUAN DE VELASCO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060354', 'canton' => '0603', 'nombre' => 'SANTIAGO DE QUITO', 'tipo' => 'RURAL'],

            // CHAMBO - CHIMBORAZO
            ['codigo_inec' => '060450', 'canton' => '0604', 'nombre' => 'CHAMBO', 'tipo' => 'URBANA'],

            // CHUNCHI - CHIMBORAZO
            ['codigo_inec' => '060550', 'canton' => '0605', 'nombre' => 'CHUNCHI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060551', 'canton' => '0605', 'nombre' => 'CAPZOL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060552', 'canton' => '0605', 'nombre' => 'COMPUD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060553', 'canton' => '0605', 'nombre' => 'GONZOL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060554', 'canton' => '0605', 'nombre' => 'LLAGOS', 'tipo' => 'RURAL'],

            // GUAMOTE - CHIMBORAZO
            ['codigo_inec' => '060650', 'canton' => '0606', 'nombre' => 'GUAMOTE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060651', 'canton' => '0606', 'nombre' => 'CEBADAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060652', 'canton' => '0606', 'nombre' => 'PALMIRA', 'tipo' => 'RURAL'],

            // GUANO - CHIMBORAZO
            ['codigo_inec' => '060750', 'canton' => '0607', 'nombre' => 'GUANO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060751', 'canton' => '0607', 'nombre' => 'GUANANDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060752', 'canton' => '0607', 'nombre' => 'ILAPO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060753', 'canton' => '0607', 'nombre' => 'LA PROVIDENCIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060754', 'canton' => '0607', 'nombre' => 'SAN ANDRÉS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060755', 'canton' => '0607', 'nombre' => 'SAN GERARDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060756', 'canton' => '0607', 'nombre' => 'SAN ISIDRO DE PATULÚ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060757', 'canton' => '0607', 'nombre' => 'SAN JOSÉ DEL CHAZO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060758', 'canton' => '0607', 'nombre' => 'SANTA FÉ DE GALÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060759', 'canton' => '0607', 'nombre' => 'VALPARAISO', 'tipo' => 'RURAL'],

            // PALLATANGA - CHIMBORAZO
            ['codigo_inec' => '060850', 'canton' => '0608', 'nombre' => 'PALLATANGA', 'tipo' => 'URBANA'],

            // PENIPE - CHIMBORAZO
            ['codigo_inec' => '060950', 'canton' => '0609', 'nombre' => 'PENIPE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '060951', 'canton' => '0609', 'nombre' => 'EL ALTAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060952', 'canton' => '0609', 'nombre' => 'MATUS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060953', 'canton' => '0609', 'nombre' => 'PUELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060954', 'canton' => '0609', 'nombre' => 'SAN ANTONIO DE BAYUSHIG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060955', 'canton' => '0609', 'nombre' => 'LA CANDELARIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '060956', 'canton' => '0609', 'nombre' => 'BILBAO', 'tipo' => 'RURAL'],

            // CUMANDÁ - CHIMBORAZO
            ['codigo_inec' => '061050', 'canton' => '0610', 'nombre' => 'CUMANDÁ', 'tipo' => 'URBANA'],

            // MACHALA - EL ORO
            ['codigo_inec' => '070150', 'canton' => '0701', 'nombre' => 'MACHALA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070152', 'canton' => '0701', 'nombre' => 'EL RETIRO', 'tipo' => 'RURAL'],

            // ARENILLAS - EL ORO
            ['codigo_inec' => '070250', 'canton' => '0702', 'nombre' => 'ARENILLAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070251', 'canton' => '0702', 'nombre' => 'CHACRAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070254', 'canton' => '0702', 'nombre' => 'PALMALES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070255', 'canton' => '0702', 'nombre' => 'CARCABÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070256', 'canton' => '0702', 'nombre' => 'LA CUCA', 'tipo' => 'RURAL'],

            // ATAHUALPA - EL ORO
            ['codigo_inec' => '070350', 'canton' => '0703', 'nombre' => 'PACCHA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070351', 'canton' => '0703', 'nombre' => 'AYAPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070352', 'canton' => '0703', 'nombre' => 'CORDONCILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070353', 'canton' => '0703', 'nombre' => 'MILAGRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070354', 'canton' => '0703', 'nombre' => 'SAN JOSÉ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070355', 'canton' => '0703', 'nombre' => 'SAN JUAN DE CERRO AZUL', 'tipo' => 'RURAL'],

            // BALSAS - EL ORO
            ['codigo_inec' => '070450', 'canton' => '0704', 'nombre' => 'BALSAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070451', 'canton' => '0704', 'nombre' => 'BELLAMARÍA', 'tipo' => 'RURAL'],

            // CHILLA - EL ORO
            ['codigo_inec' => '070550', 'canton' => '0705', 'nombre' => 'CHILLA', 'tipo' => 'URBANA'],

            // EL GUABO - EL ORO
            ['codigo_inec' => '070650', 'canton' => '0706', 'nombre' => 'EL GUABO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070651', 'canton' => '0706', 'nombre' => 'BARBONES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070652', 'canton' => '0706', 'nombre' => 'LA IBERIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070653', 'canton' => '0706', 'nombre' => 'TENDALES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070654', 'canton' => '0706', 'nombre' => 'RÍO BONITO', 'tipo' => 'RURAL'],

            // HUAQUILLAS - EL ORO
            ['codigo_inec' => '070750', 'canton' => '0707', 'nombre' => 'HUAQUILLAS', 'tipo' => 'URBANA'],

            // MARCABELÍ - EL ORO
            ['codigo_inec' => '070850', 'canton' => '0708', 'nombre' => 'MARCABELÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070851', 'canton' => '0708', 'nombre' => 'EL INGENIO', 'tipo' => 'RURAL'],

            // PASAJE - EL ORO
            ['codigo_inec' => '070950', 'canton' => '0709', 'nombre' => 'PASAJE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '070951', 'canton' => '0709', 'nombre' => 'BUENAVISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070952', 'canton' => '0709', 'nombre' => 'CASACAY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070953', 'canton' => '0709', 'nombre' => 'LA PEAÑA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070954', 'canton' => '0709', 'nombre' => 'PROGRESO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070955', 'canton' => '0709', 'nombre' => 'UZHCURRUMI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '070956', 'canton' => '0709', 'nombre' => 'CAÑAQUEMADA', 'tipo' => 'RURAL'],

            // PIÑAS - EL ORO
            ['codigo_inec' => '071050', 'canton' => '0710', 'nombre' => 'PIÑAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '071051', 'canton' => '0710', 'nombre' => 'CAPIRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071052', 'canton' => '0710', 'nombre' => 'LA BOCANA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071053', 'canton' => '0710', 'nombre' => 'MOROMORO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071054', 'canton' => '0710', 'nombre' => 'PIEDRAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071055', 'canton' => '0710', 'nombre' => 'SAN ROQUE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071056', 'canton' => '0710', 'nombre' => 'SARACAY', 'tipo' => 'RURAL'],

            // PORTOVELO - EL ORO
            ['codigo_inec' => '071150', 'canton' => '0711', 'nombre' => 'PORTOVELO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '071151', 'canton' => '0711', 'nombre' => 'CURTINCAPA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071152', 'canton' => '0711', 'nombre' => 'MORALES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071153', 'canton' => '0711', 'nombre' => 'SALATÍ', 'tipo' => 'RURAL'],

            // SANTA ROSA - EL ORO
            ['codigo_inec' => '071250', 'canton' => '0712', 'nombre' => 'SANTA ROSA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '071251', 'canton' => '0712', 'nombre' => 'BELLAVISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071252', 'canton' => '0712', 'nombre' => 'JAMBELÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071253', 'canton' => '0712', 'nombre' => 'LA AVANZADA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071254', 'canton' => '0712', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071255', 'canton' => '0712', 'nombre' => 'TORATA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071256', 'canton' => '0712', 'nombre' => 'VICTORIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071257', 'canton' => '0712', 'nombre' => 'BELLAMARÍA', 'tipo' => 'RURAL'],

            // ZARUMA - EL ORO
            ['codigo_inec' => '071350', 'canton' => '0713', 'nombre' => 'ZARUMA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '071351', 'canton' => '0713', 'nombre' => 'ABAÑÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071352', 'canton' => '0713', 'nombre' => 'ARCAPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071353', 'canton' => '0713', 'nombre' => 'GUANAZÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071354', 'canton' => '0713', 'nombre' => 'GUIZHAGUIÑA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071355', 'canton' => '0713', 'nombre' => 'HUERTAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071356', 'canton' => '0713', 'nombre' => 'MALVAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071357', 'canton' => '0713', 'nombre' => 'MULUNCAY GRANDE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071358', 'canton' => '0713', 'nombre' => 'SINSAO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071359', 'canton' => '0713', 'nombre' => 'SALVIAS', 'tipo' => 'RURAL'],

            // LAS LAJAS - EL ORO
            ['codigo_inec' => '071450', 'canton' => '0714', 'nombre' => 'LA VICTORIA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '071451', 'canton' => '0714', 'nombre' => 'LA LIBERTAD', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071452', 'canton' => '0714', 'nombre' => 'EL PARAÍSO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '071453', 'canton' => '0714', 'nombre' => 'SAN ISIDRO', 'tipo' => 'RURAL'],

            // ESMERALDAS - ESMERALDAS
            ['codigo_inec' => '080150', 'canton' => '0801', 'nombre' => 'ESMERALDAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080152', 'canton' => '0801', 'nombre' => 'CAMARONES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080153', 'canton' => '0801', 'nombre' => 'CORONEL CARLOS CONCHA TORRES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080154', 'canton' => '0801', 'nombre' => 'CHINCA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080159', 'canton' => '0801', 'nombre' => 'MAJUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080163', 'canton' => '0801', 'nombre' => 'SAN MATEO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080165', 'canton' => '0801', 'nombre' => 'TABIAZO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080166', 'canton' => '0801', 'nombre' => 'TACHINA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080168', 'canton' => '0801', 'nombre' => 'VUELTA LARGA', 'tipo' => 'RURAL'],

            // ELOY ALFARO - ESMERALDAS
            ['codigo_inec' => '080250', 'canton' => '0802', 'nombre' => 'VALDEZ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080251', 'canton' => '0802', 'nombre' => 'ANCHAYACU', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080252', 'canton' => '0802', 'nombre' => 'ATAHUALPA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080253', 'canton' => '0802', 'nombre' => 'BORBÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080254', 'canton' => '0802', 'nombre' => 'LA TOLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080255', 'canton' => '0802', 'nombre' => 'LUIS VARGAS TORRES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080256', 'canton' => '0802', 'nombre' => 'MALDONADO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080257', 'canton' => '0802', 'nombre' => 'PAMPANAL DE BOLÍVAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080258', 'canton' => '0802', 'nombre' => 'SAN FRANCISCO DE ONZOLE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080259', 'canton' => '0802', 'nombre' => 'SANTO DOMINGO DE ONZOLE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080260', 'canton' => '0802', 'nombre' => 'SELVA ALEGRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080261', 'canton' => '0802', 'nombre' => 'TELEMBÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080262', 'canton' => '0802', 'nombre' => 'COLÓN ELOY DEL MARÍA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080263', 'canton' => '0802', 'nombre' => 'SAN JOSÉ DE CAYAPAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080264', 'canton' => '0802', 'nombre' => 'TIMBIRÉ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080265', 'canton' => '0802', 'nombre' => 'SANTA LUCÍA DE LAS PEÑAS', 'tipo' => 'RURAL'],

            // MUISNE - ESMERALDAS
            ['codigo_inec' => '080350', 'canton' => '0803', 'nombre' => 'MUISNE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080351', 'canton' => '0803', 'nombre' => 'BOLÍVAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080352', 'canton' => '0803', 'nombre' => 'DAULE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080353', 'canton' => '0803', 'nombre' => 'GALERA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080354', 'canton' => '0803', 'nombre' => 'QUINGUE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080355', 'canton' => '0803', 'nombre' => 'SÁLIMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080356', 'canton' => '0803', 'nombre' => 'SAN FRANCISCO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080357', 'canton' => '0803', 'nombre' => 'SAN GREGORIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080358', 'canton' => '0803', 'nombre' => 'SAN JOSÉ DE CHAMANGA', 'tipo' => 'RURAL'],

            // QUININDÉ - ESMERALDAS
            ['codigo_inec' => '080450', 'canton' => '0804', 'nombre' => 'ROSA ZÁRATE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080451', 'canton' => '0804', 'nombre' => 'CUBE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080452', 'canton' => '0804', 'nombre' => 'CHURA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080453', 'canton' => '0804', 'nombre' => 'MALIMPIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080454', 'canton' => '0804', 'nombre' => 'VICHE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080455', 'canton' => '0804', 'nombre' => 'LA UNIÓN', 'tipo' => 'RURAL'],

            // SAN LORENZO - ESMERALDAS
            ['codigo_inec' => '080550', 'canton' => '0805', 'nombre' => 'SAN LORENZO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080551', 'canton' => '0805', 'nombre' => 'ALTO TAMBO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080552', 'canton' => '0805', 'nombre' => 'ANCÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080553', 'canton' => '0805', 'nombre' => 'CALDERÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080554', 'canton' => '0805', 'nombre' => 'CARONDELET', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080555', 'canton' => '0805', 'nombre' => '5 DE JUNIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080556', 'canton' => '0805', 'nombre' => 'CONCEPCIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080557', 'canton' => '0805', 'nombre' => 'MATAJE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080558', 'canton' => '0805', 'nombre' => 'SAN JAVIER DE CACHAVÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080559', 'canton' => '0805', 'nombre' => 'SANTA RITA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080560', 'canton' => '0805', 'nombre' => 'TAMBILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080561', 'canton' => '0805', 'nombre' => 'TULULBÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080562', 'canton' => '0805', 'nombre' => 'URBINA', 'tipo' => 'RURAL'],

            // ATACAMES - ESMERALDAS
            ['codigo_inec' => '080650', 'canton' => '0806', 'nombre' => 'ATACAMES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080651', 'canton' => '0806', 'nombre' => 'LA UNIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080652', 'canton' => '0806', 'nombre' => 'SÚA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080653', 'canton' => '0806', 'nombre' => 'TONCHIGÜE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080654', 'canton' => '0806', 'nombre' => 'TONSUPA', 'tipo' => 'RURAL'],

            // RIOVERDE - ESMERALDAS
            ['codigo_inec' => '080750', 'canton' => '0807', 'nombre' => 'RIOVERDE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '080751', 'canton' => '0807', 'nombre' => 'CHONTADURO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080752', 'canton' => '0807', 'nombre' => 'CHUMUNDÉ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080753', 'canton' => '0807', 'nombre' => 'LAGARTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080754', 'canton' => '0807', 'nombre' => 'MONTALVO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '080755', 'canton' => '0807', 'nombre' => 'ROCAFUERTE', 'tipo' => 'RURAL'],

            // GUAYAQUIL - GUAYAS
            ['codigo_inec' => '090150', 'canton' => '0901', 'nombre' => 'GUAYAQUIL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '090152', 'canton' => '0901', 'nombre' => 'JUAN GÓMEZ RENDÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090153', 'canton' => '0901', 'nombre' => 'MORRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090156', 'canton' => '0901', 'nombre' => 'POSORJA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090157', 'canton' => '0901', 'nombre' => 'PUNÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090158', 'canton' => '0901', 'nombre' => 'TENGUEL', 'tipo' => 'RURAL'],

            // ALFREDO BAQUERIZO MORENO (JUJÁN) - GUAYAS
            ['codigo_inec' => '090250', 'canton' => '0902', 'nombre' => 'ALFREDO BAQUERIZO MORENO (JUJÁN)', 'tipo' => 'URBANA'],

            // BALAO - GUAYAS
            ['codigo_inec' => '090350', 'canton' => '0903', 'nombre' => 'BALAO', 'tipo' => 'URBANA'],

            // BALZAR - GUAYAS
            ['codigo_inec' => '090450', 'canton' => '0904', 'nombre' => 'BALZAR', 'tipo' => 'URBANA'],

            // COLIMES - GUAYAS
            ['codigo_inec' => '090550', 'canton' => '0905', 'nombre' => 'COLIMES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '090551', 'canton' => '0905', 'nombre' => 'SAN JACINTO', 'tipo' => 'RURAL'],

            // DAULE - GUAYAS
            ['codigo_inec' => '090650', 'canton' => '0906', 'nombre' => 'DAULE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '090652', 'canton' => '0906', 'nombre' => 'JUAN BAUTISTA AGUIRRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090653', 'canton' => '0906', 'nombre' => 'LAUREL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090654', 'canton' => '0906', 'nombre' => 'LIMONAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090656', 'canton' => '0906', 'nombre' => 'LOS LOJAS', 'tipo' => 'RURAL'],

            // DURÁN - GUAYAS
            ['codigo_inec' => '090750', 'canton' => '0907', 'nombre' => 'ELOY ALFARO', 'tipo' => 'URBANA'],

            // EL EMPALME - GUAYAS
            ['codigo_inec' => '090850', 'canton' => '0908', 'nombre' => 'VELASCO IBARRA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '090851', 'canton' => '0908', 'nombre' => 'GUAYAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '090852', 'canton' => '0908', 'nombre' => 'EL ROSARIO', 'tipo' => 'RURAL'],

            // EL TRIUNFO - GUAYAS
            ['codigo_inec' => '090950', 'canton' => '0909', 'nombre' => 'EL TRIUNFO', 'tipo' => 'URBANA'],

            // MILAGRO - GUAYAS
            ['codigo_inec' => '091050', 'canton' => '0910', 'nombre' => 'MILAGRO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '091051', 'canton' => '0910', 'nombre' => 'CHOBO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091053', 'canton' => '0910', 'nombre' => 'MARISCAL SUCRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091054', 'canton' => '0910', 'nombre' => 'ROBERTO ASTUDILLO', 'tipo' => 'RURAL'],

            // NARANJAL - GUAYAS
            ['codigo_inec' => '091150', 'canton' => '0911', 'nombre' => 'NARANJAL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '091151', 'canton' => '0911', 'nombre' => 'JESÚS MARÍA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091152', 'canton' => '0911', 'nombre' => 'SAN CARLOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091153', 'canton' => '0911', 'nombre' => 'SANTA ROSA DE FLANDES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091154', 'canton' => '0911', 'nombre' => 'TAURA', 'tipo' => 'RURAL'],

            // NARANJITO - GUAYAS
            ['codigo_inec' => '091250', 'canton' => '0912', 'nombre' => 'NARANJITO', 'tipo' => 'URBANA'],

            // PALESTINA - GUAYAS
            ['codigo_inec' => '091350', 'canton' => '0913', 'nombre' => 'PALESTINA', 'tipo' => 'URBANA'],

            // PEDRO CARBO - GUAYAS
            ['codigo_inec' => '091450', 'canton' => '0914', 'nombre' => 'PEDRO CARBO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '091451', 'canton' => '0914', 'nombre' => 'VALLE DE LA VIRGEN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091452', 'canton' => '0914', 'nombre' => 'SABANILLA', 'tipo' => 'RURAL'],

            // SAMBORONDÓN - GUAYAS
            ['codigo_inec' => '091650', 'canton' => '0916', 'nombre' => 'SAMBORONDÓN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '091651', 'canton' => '0916', 'nombre' => 'TARIFA', 'tipo' => 'RURAL'],

            // SANTA LUCÍA - GUAYAS
            ['codigo_inec' => '091850', 'canton' => '0918', 'nombre' => 'SANTA LUCÍA', 'tipo' => 'URBANA'],

            // SALITRE - GUAYAS
            ['codigo_inec' => '091950', 'canton' => '0919', 'nombre' => 'EL SALITRE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '091951', 'canton' => '0919', 'nombre' => 'GENERAL VERNAZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091952', 'canton' => '0919', 'nombre' => 'LA VICTORIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '091953', 'canton' => '0919', 'nombre' => 'JUNQUILLAL', 'tipo' => 'RURAL'],

            // SAN JACINTO DE YAGUACHI - GUAYAS
            ['codigo_inec' => '092050', 'canton' => '0920', 'nombre' => 'SAN JACINTO DE YAGUACHI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '092053', 'canton' => '0920', 'nombre' => 'GENERAL PEDRO J. MONTERO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '092055', 'canton' => '0920', 'nombre' => 'YAGUACHI VIEJO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '092056', 'canton' => '0920', 'nombre' => 'VIRGEN DE FÁTIMA', 'tipo' => 'RURAL'],

            // PLAYAS - GUAYAS
            ['codigo_inec' => '092150', 'canton' => '0921', 'nombre' => 'GENERAL VILLAMIL', 'tipo' => 'URBANA'],

            // SIMÓN BOLÍVAR - GUAYAS
            ['codigo_inec' => '092250', 'canton' => '0922', 'nombre' => 'SIMÓN BOLÍVAR', 'tipo' => 'URBANA'],
            ['codigo_inec' => '092251', 'canton' => '0922', 'nombre' => 'CORONEL LORENZO DE GARAYCOA', 'tipo' => 'RURAL'],

            // CORONEL MARCELINO MARIDUEÑA - GUAYAS
            ['codigo_inec' => '092350', 'canton' => '0923', 'nombre' => 'CORONEL MARCELINO MARIDUEÑA', 'tipo' => 'URBANA'],

            // LOMAS DE SARGENTILLO - GUAYAS
            ['codigo_inec' => '092450', 'canton' => '0924', 'nombre' => 'LOMAS DE SARGENTILLO', 'tipo' => 'URBANA'],

            // NOBOL - GUAYAS
            ['codigo_inec' => '092550', 'canton' => '0925', 'nombre' => 'NARCISA DE JESÚS', 'tipo' => 'URBANA'],

            // GENERAL  ANTONIO ELIZALDE - GUAYAS
            ['codigo_inec' => '092750', 'canton' => '0927', 'nombre' => 'GENERAL ANTONIO ELIZALDE', 'tipo' => 'URBANA'],

            // ISIDRO AYORA - GUAYAS
            ['codigo_inec' => '092850', 'canton' => '0928', 'nombre' => 'ISIDRO AYORA', 'tipo' => 'URBANA'],

            // IBARRA - IMBABURA
            ['codigo_inec' => '100150', 'canton' => '1001', 'nombre' => 'SAN MIGUEL DE IBARRA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '100151', 'canton' => '1001', 'nombre' => 'AMBUQUÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100152', 'canton' => '1001', 'nombre' => 'ANGOCHAGUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100153', 'canton' => '1001', 'nombre' => 'LA CAROLINA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100154', 'canton' => '1001', 'nombre' => 'LA ESPERANZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100155', 'canton' => '1001', 'nombre' => 'LITA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100156', 'canton' => '1001', 'nombre' => 'SALINAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100157', 'canton' => '1001', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],

            // ANTONIO ANTE - IMBABURA
            ['codigo_inec' => '100250', 'canton' => '1002', 'nombre' => 'ATUNTAQUI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '100251', 'canton' => '1002', 'nombre' => 'IMBAYA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100252', 'canton' => '1002', 'nombre' => 'SAN FRANCISCO DE NATABUELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100253', 'canton' => '1002', 'nombre' => 'SAN JOSÉ DE CHALTURA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100254', 'canton' => '1002', 'nombre' => 'SAN ROQUE', 'tipo' => 'RURAL'],

            // COTACACHI - IMBABURA
            ['codigo_inec' => '100350', 'canton' => '1003', 'nombre' => 'COTACACHI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '100351', 'canton' => '1003', 'nombre' => 'APUELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100352', 'canton' => '1003', 'nombre' => 'GARCÍA MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100353', 'canton' => '1003', 'nombre' => 'IMANTAG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100354', 'canton' => '1003', 'nombre' => 'PEÑAHERRERA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100355', 'canton' => '1003', 'nombre' => 'PLAZA GUTIÉRREZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100356', 'canton' => '1003', 'nombre' => 'QUIROGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100357', 'canton' => '1003', 'nombre' => 'SEIS DE JULIO DE CUELLAJE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100358', 'canton' => '1003', 'nombre' => 'VACAS GALINDO', 'tipo' => 'RURAL'],

            // OTAVALO - IMBABURA
            ['codigo_inec' => '100450', 'canton' => '1004', 'nombre' => 'OTAVALO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '100451', 'canton' => '1004', 'nombre' => 'DR. MIGUEL EGAS CABEZAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100452', 'canton' => '1004', 'nombre' => 'EUGENIO ESPEJO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100453', 'canton' => '1004', 'nombre' => 'GONZÁLEZ SUÁREZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100454', 'canton' => '1004', 'nombre' => 'PATAQUÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100455', 'canton' => '1004', 'nombre' => 'SAN JOSÉ DE QUICHINCHE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100456', 'canton' => '1004', 'nombre' => 'SAN JUAN DE ILUMÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100457', 'canton' => '1004', 'nombre' => 'SAN PABLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100458', 'canton' => '1004', 'nombre' => 'SAN RAFAEL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100459', 'canton' => '1004', 'nombre' => 'SELVA ALEGRE', 'tipo' => 'RURAL'],

            // PIMAMPIRO - IMBABURA
            ['codigo_inec' => '100550', 'canton' => '1005', 'nombre' => 'PIMAMPIRO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '100551', 'canton' => '1005', 'nombre' => 'CHUGÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100552', 'canton' => '1005', 'nombre' => 'MARIANO ACOSTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100553', 'canton' => '1005', 'nombre' => 'SAN FRANCISCO DE SIGSIPAMBA', 'tipo' => 'RURAL'],

            // SAN MIGUEL DE URCUQUÍ - IMBABURA
            ['codigo_inec' => '100650', 'canton' => '1006', 'nombre' => 'URCUQUÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '100651', 'canton' => '1006', 'nombre' => 'CAHUASQUÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100652', 'canton' => '1006', 'nombre' => 'LA MERCED DE BUENOS AIRES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100653', 'canton' => '1006', 'nombre' => 'PABLO ARENAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100654', 'canton' => '1006', 'nombre' => 'SAN BLAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '100655', 'canton' => '1006', 'nombre' => 'TUMBABIRO', 'tipo' => 'RURAL'],

            // LOJA - LOJA
            ['codigo_inec' => '110150', 'canton' => '1101', 'nombre' => 'LOJA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110151', 'canton' => '1101', 'nombre' => 'CHANTACO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110152', 'canton' => '1101', 'nombre' => 'CHUQUIRIBAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110153', 'canton' => '1101', 'nombre' => 'EL CISNE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110154', 'canton' => '1101', 'nombre' => 'GUALEL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110155', 'canton' => '1101', 'nombre' => 'JIMBILLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110156', 'canton' => '1101', 'nombre' => 'MALACATOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110157', 'canton' => '1101', 'nombre' => 'SAN LUCAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110158', 'canton' => '1101', 'nombre' => 'SAN PEDRO DE VILCABAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110159', 'canton' => '1101', 'nombre' => 'SANTIAGO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110160', 'canton' => '1101', 'nombre' => 'TAQUIL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110161', 'canton' => '1101', 'nombre' => 'VILCABAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110162', 'canton' => '1101', 'nombre' => 'YANGANA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110163', 'canton' => '1101', 'nombre' => 'QUINARA', 'tipo' => 'RURAL'],

            // CALVAS - LOJA
            ['codigo_inec' => '110250', 'canton' => '1102', 'nombre' => 'CARIAMANGA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110251', 'canton' => '1102', 'nombre' => 'COLAISACA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110252', 'canton' => '1102', 'nombre' => 'EL LUCERO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110253', 'canton' => '1102', 'nombre' => 'UTUANA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110254', 'canton' => '1102', 'nombre' => 'SANGUILLÍN', 'tipo' => 'RURAL'],

            // CATAMAYO - LOJA
            ['codigo_inec' => '110350', 'canton' => '1103', 'nombre' => 'CATAMAYO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110351', 'canton' => '1103', 'nombre' => 'EL TAMBO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110352', 'canton' => '1103', 'nombre' => 'GUAYQUICHUMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110353', 'canton' => '1103', 'nombre' => 'SAN PEDRO DE LA BENDITA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110354', 'canton' => '1103', 'nombre' => 'ZAMBI', 'tipo' => 'RURAL'],

            // CELICA - LOJA
            ['codigo_inec' => '110450', 'canton' => '1104', 'nombre' => 'CELICA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110451', 'canton' => '1104', 'nombre' => 'CRUZPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110455', 'canton' => '1104', 'nombre' => 'PÓZUL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110456', 'canton' => '1104', 'nombre' => 'SABANILLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110457', 'canton' => '1104', 'nombre' => 'TENIENTE MAXIMILIANO RODRÍGUEZ LOAIZA', 'tipo' => 'RURAL'],

            // CHAGUARPAMBA - LOJA
            ['codigo_inec' => '110550', 'canton' => '1105', 'nombre' => 'CHAGUARPAMBA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110551', 'canton' => '1105', 'nombre' => 'BUENAVISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110552', 'canton' => '1105', 'nombre' => 'EL ROSARIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110553', 'canton' => '1105', 'nombre' => 'SANTA RUFINA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110554', 'canton' => '1105', 'nombre' => 'AMARILLOS', 'tipo' => 'RURAL'],

            // ESPÍNDOLA - LOJA
            ['codigo_inec' => '110650', 'canton' => '1106', 'nombre' => 'AMALUZA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110651', 'canton' => '1106', 'nombre' => 'BELLAVISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110652', 'canton' => '1106', 'nombre' => 'JIMBURA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110653', 'canton' => '1106', 'nombre' => 'SANTA TERESITA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110654', 'canton' => '1106', 'nombre' => '27 DE ABRIL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110655', 'canton' => '1106', 'nombre' => 'EL INGENIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110656', 'canton' => '1106', 'nombre' => 'EL AIRO', 'tipo' => 'RURAL'],

            // GONZANAMÁ - LOJA
            ['codigo_inec' => '110750', 'canton' => '1107', 'nombre' => 'GONZANAMÁ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110751', 'canton' => '1107', 'nombre' => 'CHANGAIMINA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110753', 'canton' => '1107', 'nombre' => 'NAMBACOLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110754', 'canton' => '1107', 'nombre' => 'PURUNUMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110756', 'canton' => '1107', 'nombre' => 'SACAPALCA', 'tipo' => 'RURAL'],

            // MACARÁ - LOJA
            ['codigo_inec' => '110850', 'canton' => '1108', 'nombre' => 'MACARÁ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110851', 'canton' => '1108', 'nombre' => 'LARAMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110852', 'canton' => '1108', 'nombre' => 'LA VICTORIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110853', 'canton' => '1108', 'nombre' => 'SABIANGO', 'tipo' => 'RURAL'],

            // PALTAS - LOJA
            ['codigo_inec' => '110950', 'canton' => '1109', 'nombre' => 'CATACOCHA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '110951', 'canton' => '1109', 'nombre' => 'CANGONAMÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110952', 'canton' => '1109', 'nombre' => 'GUACHANAMÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110954', 'canton' => '1109', 'nombre' => 'LAURO GUERRERO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110956', 'canton' => '1109', 'nombre' => 'ORIANGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110957', 'canton' => '1109', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110958', 'canton' => '1109', 'nombre' => 'CASANGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '110959', 'canton' => '1109', 'nombre' => 'YAMANA', 'tipo' => 'RURAL'],

            // PUYANGO - LOJA
            ['codigo_inec' => '111050', 'canton' => '1110', 'nombre' => 'ALAMOR', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111051', 'canton' => '1110', 'nombre' => 'CIANO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111052', 'canton' => '1110', 'nombre' => 'EL ARENAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111053', 'canton' => '1110', 'nombre' => 'EL LIMO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111054', 'canton' => '1110', 'nombre' => 'MERCADILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111055', 'canton' => '1110', 'nombre' => 'VICENTINO', 'tipo' => 'RURAL'],

            // SARAGURO - LOJA
            ['codigo_inec' => '111150', 'canton' => '1111', 'nombre' => 'SARAGURO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111151', 'canton' => '1111', 'nombre' => 'EL PARAÍSO DE CELEN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111152', 'canton' => '1111', 'nombre' => 'EL TABLÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111153', 'canton' => '1111', 'nombre' => 'LLUZHAPA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111154', 'canton' => '1111', 'nombre' => 'MANÚ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111155', 'canton' => '1111', 'nombre' => 'SAN ANTONIO DE QUMBE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111156', 'canton' => '1111', 'nombre' => 'SAN PABLO DE TENTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111157', 'canton' => '1111', 'nombre' => 'SAN SEBASTIÁN DE YÚLUC', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111158', 'canton' => '1111', 'nombre' => 'SELVA ALEGRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111159', 'canton' => '1111', 'nombre' => 'URDANETA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111160', 'canton' => '1111', 'nombre' => 'SUMAYPAMBA', 'tipo' => 'RURAL'],

            // SOZORANGA - LOJA
            ['codigo_inec' => '111250', 'canton' => '1112', 'nombre' => 'SOZORANGA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111251', 'canton' => '1112', 'nombre' => 'NUEVA FÁTIMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111252', 'canton' => '1112', 'nombre' => 'TACAMOROS', 'tipo' => 'RURAL'],

            // ZAPOTILLO - LOJA
            ['codigo_inec' => '111350', 'canton' => '1113', 'nombre' => 'ZAPOTILLO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111351', 'canton' => '1113', 'nombre' => 'MANGAHURCO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111352', 'canton' => '1113', 'nombre' => 'GARZAREAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111353', 'canton' => '1113', 'nombre' => 'LIMONES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111354', 'canton' => '1113', 'nombre' => 'PALETILLAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111355', 'canton' => '1113', 'nombre' => 'BOLASPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111356', 'canton' => '1113', 'nombre' => 'CAZADEROS', 'tipo' => 'RURAL'],

            // PINDAL - LOJA
            ['codigo_inec' => '111450', 'canton' => '1114', 'nombre' => 'PINDAL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111451', 'canton' => '1114', 'nombre' => 'CHAQUINAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111452', 'canton' => '1114', 'nombre' => '12 DE DICIEMBRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111453', 'canton' => '1114', 'nombre' => 'MILAGROS', 'tipo' => 'RURAL'],

            // QUILANGA - LOJA
            ['codigo_inec' => '111550', 'canton' => '1115', 'nombre' => 'QUILANGA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111551', 'canton' => '1115', 'nombre' => 'FUNDOCHAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '111552', 'canton' => '1115', 'nombre' => 'SAN ANTONIO DE LAS ARADAS', 'tipo' => 'RURAL'],

            // OLMEDO - LOJA
            ['codigo_inec' => '111650', 'canton' => '1116', 'nombre' => 'OLMEDO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '111651', 'canton' => '1116', 'nombre' => 'LA TINGUE', 'tipo' => 'RURAL'],

            // BABAHOYO - LOS RÍOS
            ['codigo_inec' => '120150', 'canton' => '1201', 'nombre' => 'BABAHOYO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120152', 'canton' => '1201', 'nombre' => 'CARACOL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120153', 'canton' => '1201', 'nombre' => 'FEBRES CORDERO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120154', 'canton' => '1201', 'nombre' => 'PIMOCHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120155', 'canton' => '1201', 'nombre' => 'LA UNIÓN', 'tipo' => 'RURAL'],

            // BABA - LOS RÍOS
            ['codigo_inec' => '120250', 'canton' => '1202', 'nombre' => 'BABA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120251', 'canton' => '1202', 'nombre' => 'GUARE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120252', 'canton' => '1202', 'nombre' => 'ISLA DE BEJUCAL', 'tipo' => 'RURAL'],

            // MONTALVO - LOS RÍOS
            ['codigo_inec' => '120350', 'canton' => '1203', 'nombre' => 'MONTALVO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120351', 'canton' => '1203', 'nombre' => 'LA ESMERALDA', 'tipo' => 'RURAL'],

            // PUEBLOVIEJO - LOS RÍOS
            ['codigo_inec' => '120450', 'canton' => '1204', 'nombre' => 'PUEBLOVIEJO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120451', 'canton' => '1204', 'nombre' => 'PUERTO PECHICHE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120452', 'canton' => '1204', 'nombre' => 'SAN JUAN', 'tipo' => 'RURAL'],

            // QUEVEDO - LOS RÍOS
            ['codigo_inec' => '120550', 'canton' => '1205', 'nombre' => 'QUEVEDO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120553', 'canton' => '1205', 'nombre' => 'SAN CARLOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120555', 'canton' => '1205', 'nombre' => 'LA ESPERANZA', 'tipo' => 'RURAL'],

            // URDANETA - LOS RÍOS
            ['codigo_inec' => '120650', 'canton' => '1206', 'nombre' => 'CATARAMA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120651', 'canton' => '1206', 'nombre' => 'RICAURTE', 'tipo' => 'RURAL'],

            // VENTANAS - LOS RÍOS
            ['codigo_inec' => '120750', 'canton' => '1207', 'nombre' => 'VENTANAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120752', 'canton' => '1207', 'nombre' => 'ZAPOTAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120753', 'canton' => '1207', 'nombre' => 'CHACARITA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '120754', 'canton' => '1207', 'nombre' => 'LOS ÁNGELES', 'tipo' => 'RURAL'],

            // VINCES - LOS RÍOS
            ['codigo_inec' => '120850', 'canton' => '1208', 'nombre' => 'VINCES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '120851', 'canton' => '1208', 'nombre' => 'ANTONIO SOTOMAYOR', 'tipo' => 'RURAL'],

            // PALENQUE - LOS RÍOS
            ['codigo_inec' => '120950', 'canton' => '1209', 'nombre' => 'PALENQUE', 'tipo' => 'URBANA'],

            // BUENA FE - LOS RÍOS
            ['codigo_inec' => '121050', 'canton' => '1210', 'nombre' => 'SAN JACINTO DE BUENA FE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '121051', 'canton' => '1210', 'nombre' => 'PATRICIA PILAR', 'tipo' => 'RURAL'],

            // VALENCIA - LOS RÍOS
            ['codigo_inec' => '121150', 'canton' => '1211', 'nombre' => 'VALENCIA', 'tipo' => 'URBANA'],

            // MOCACHE - LOS RÍOS
            ['codigo_inec' => '121250', 'canton' => '1212', 'nombre' => 'MOCACHE', 'tipo' => 'URBANA'],

            // QUINSALOMA - LOS RÍOS
            ['codigo_inec' => '121350', 'canton' => '1213', 'nombre' => 'QUINSALOMA', 'tipo' => 'URBANA'],

            // PORTOVIEJO - MANABÍ
            ['codigo_inec' => '130150', 'canton' => '1301', 'nombre' => 'PORTOVIEJO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130151', 'canton' => '1301', 'nombre' => 'ABDÓN CALDERÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130152', 'canton' => '1301', 'nombre' => 'ALHAJUELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130153', 'canton' => '1301', 'nombre' => 'CRUCITA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130154', 'canton' => '1301', 'nombre' => 'PUEBLO NUEVO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130155', 'canton' => '1301', 'nombre' => 'RIOCHICO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130156', 'canton' => '1301', 'nombre' => 'SAN PLÁCIDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130157', 'canton' => '1301', 'nombre' => 'CHIRIJOS', 'tipo' => 'RURAL'],

            // BOLÍVAR - MANABÍ
            ['codigo_inec' => '130250', 'canton' => '1302', 'nombre' => 'CALCETA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130251', 'canton' => '1302', 'nombre' => 'MEMBRILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130252', 'canton' => '1302', 'nombre' => 'QUIROGA', 'tipo' => 'RURAL'],

            // CHONE - MANABÍ
            ['codigo_inec' => '130350', 'canton' => '1303', 'nombre' => 'CHONE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130351', 'canton' => '1303', 'nombre' => 'BOYACÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130352', 'canton' => '1303', 'nombre' => 'CANUTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130353', 'canton' => '1303', 'nombre' => 'CONVENTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130354', 'canton' => '1303', 'nombre' => 'CHIBUNGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130355', 'canton' => '1303', 'nombre' => 'ELOY ALFARO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130356', 'canton' => '1303', 'nombre' => 'RICAURTE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130357', 'canton' => '1303', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],

            // EL CARMEN - MANABÍ
            ['codigo_inec' => '130450', 'canton' => '1304', 'nombre' => 'EL CARMEN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130451', 'canton' => '1304', 'nombre' => 'WILFRIDO LOOR MOREIRA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130452', 'canton' => '1304', 'nombre' => 'SAN PEDRO DE SUMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130453', 'canton' => '1304', 'nombre' => 'SANTA MARÍA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130454', 'canton' => '1304', 'nombre' => 'EL PARAÍSO LA 14', 'tipo' => 'RURAL'],

            // FLAVIO ALFARO - MANABÍ
            ['codigo_inec' => '130550', 'canton' => '1305', 'nombre' => 'FLAVIO ALFARO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130551', 'canton' => '1305', 'nombre' => 'SAN FRANCISCO DE NOVILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130552', 'canton' => '1305', 'nombre' => 'ZAPALLO', 'tipo' => 'RURAL'],

            // JIPIJAPA - MANABÍ
            ['codigo_inec' => '130650', 'canton' => '1306', 'nombre' => 'JIPIJAPA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130651', 'canton' => '1306', 'nombre' => 'AMÉRICA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130652', 'canton' => '1306', 'nombre' => 'EL ANEGADO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130653', 'canton' => '1306', 'nombre' => 'JULCUY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130654', 'canton' => '1306', 'nombre' => 'LA UNIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130656', 'canton' => '1306', 'nombre' => 'MEMBRILLAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130657', 'canton' => '1306', 'nombre' => 'PEDRO PABLO GÓMEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130658', 'canton' => '1306', 'nombre' => 'PUERTO CAYO', 'tipo' => 'RURAL'],

            // JUNÍN - MANABÍ
            ['codigo_inec' => '130750', 'canton' => '1307', 'nombre' => 'JUNÍN', 'tipo' => 'URBANA'],

            // MANTA - MANABÍ
            ['codigo_inec' => '130850', 'canton' => '1308', 'nombre' => 'MANTA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130851', 'canton' => '1308', 'nombre' => 'SAN LORENZO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '130852', 'canton' => '1308', 'nombre' => 'SANTA MARIANITA', 'tipo' => 'RURAL'],

            // MONTECRISTI - MANABÍ
            ['codigo_inec' => '130950', 'canton' => '1309', 'nombre' => 'MONTECRISTI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '130952', 'canton' => '1309', 'nombre' => 'LA PILA', 'tipo' => 'RURAL'],

            // PAJÁN - MANABÍ
            ['codigo_inec' => '131050', 'canton' => '1310', 'nombre' => 'PAJÁN', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131051', 'canton' => '1310', 'nombre' => 'CAMPOZANO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131052', 'canton' => '1310', 'nombre' => 'CASCOL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131053', 'canton' => '1310', 'nombre' => 'GUALE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131054', 'canton' => '1310', 'nombre' => 'LASCANO', 'tipo' => 'RURAL'],

            // PICHINCHA - MANABÍ
            ['codigo_inec' => '131150', 'canton' => '1311', 'nombre' => 'PICHINCHA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131151', 'canton' => '1311', 'nombre' => 'BARRAGANETE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131152', 'canton' => '1311', 'nombre' => 'SAN SEBASTIÁN', 'tipo' => 'RURAL'],

            // ROCAFUERTE - MANABÍ
            ['codigo_inec' => '131250', 'canton' => '1312', 'nombre' => 'ROCAFUERTE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131251', 'canton' => '1312', 'nombre' => 'SOSOTE', 'tipo' => 'RURAL'],

            // SANTA ANA - MANABÍ
            ['codigo_inec' => '131350', 'canton' => '1313', 'nombre' => 'SANTA ANA DE VUELTA LARGA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131351', 'canton' => '1313', 'nombre' => 'AYACUCHO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131352', 'canton' => '1313', 'nombre' => 'HONORATO VÁSQUEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131353', 'canton' => '1313', 'nombre' => 'LA UNIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131355', 'canton' => '1313', 'nombre' => 'SAN PABLO', 'tipo' => 'RURAL'],

            // SUCRE - MANABÍ
            ['codigo_inec' => '131450', 'canton' => '1314', 'nombre' => 'BAHÍA DE CARÁQUEZ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131453', 'canton' => '1314', 'nombre' => 'CHARAPOTÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131457', 'canton' => '1314', 'nombre' => 'SAN ISIDRO', 'tipo' => 'RURAL'],

            // TOSAGUA - MANABÍ
            ['codigo_inec' => '131550', 'canton' => '1315', 'nombre' => 'TOSAGUA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131551', 'canton' => '1315', 'nombre' => 'BACHILLERO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131552', 'canton' => '1315', 'nombre' => 'ÁNGEL PEDRO GILER', 'tipo' => 'RURAL'],

            // 24 DE MAYO - MANABÍ
            ['codigo_inec' => '131650', 'canton' => '1316', 'nombre' => 'SUCRE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131651', 'canton' => '1316', 'nombre' => 'BELLAVISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131652', 'canton' => '1316', 'nombre' => 'NOBOA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131653', 'canton' => '1316', 'nombre' => 'ARQUITECTO SIXTO DURÁN BALLÉN', 'tipo' => 'RURAL'],

            // PEDERNALES - MANABÍ
            ['codigo_inec' => '131750', 'canton' => '1317', 'nombre' => 'PEDERNALES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131751', 'canton' => '1317', 'nombre' => 'COJIMÍES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131752', 'canton' => '1317', 'nombre' => 'DIEZ DE AGOSTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131753', 'canton' => '1317', 'nombre' => 'ATAHUALPA', 'tipo' => 'RURAL'],

            // OLMEDO - MANABÍ
            ['codigo_inec' => '131850', 'canton' => '1318', 'nombre' => 'OLMEDO', 'tipo' => 'URBANA'],

            // PUERTO LÓPEZ - MANABÍ
            ['codigo_inec' => '131950', 'canton' => '1319', 'nombre' => 'PUERTO LÓPEZ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '131951', 'canton' => '1319', 'nombre' => 'MACHALILLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '131952', 'canton' => '1319', 'nombre' => 'SALANGO', 'tipo' => 'RURAL'],

            // JAMA - MANABÍ
            ['codigo_inec' => '132050', 'canton' => '1320', 'nombre' => 'JAMA', 'tipo' => 'URBANA'],

            // JARAMIJÓ - MANABÍ
            ['codigo_inec' => '132150', 'canton' => '1321', 'nombre' => 'JARAMIJÓ', 'tipo' => 'URBANA'],

            // SAN VICENTE - MANABÍ
            ['codigo_inec' => '132250', 'canton' => '1322', 'nombre' => 'SAN VICENTE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '132251', 'canton' => '1322', 'nombre' => 'CANOA', 'tipo' => 'RURAL'],

            // MORONA - MORONA SANTIAGO
            ['codigo_inec' => '140150', 'canton' => '1401', 'nombre' => 'MACAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140151', 'canton' => '1401', 'nombre' => 'ALSHI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140153', 'canton' => '1401', 'nombre' => 'GENERAL PROAÑO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140156', 'canton' => '1401', 'nombre' => 'SAN ISIDRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140158', 'canton' => '1401', 'nombre' => 'SINAÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140160', 'canton' => '1401', 'nombre' => 'ZUÑA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140162', 'canton' => '1401', 'nombre' => 'CUCHAENTZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140164', 'canton' => '1401', 'nombre' => 'RÍO BLANCO', 'tipo' => 'RURAL'],

            // GUALAQUIZA - MORONA SANTIAGO
            ['codigo_inec' => '140250', 'canton' => '1402', 'nombre' => 'GUALAQUIZA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140251', 'canton' => '1402', 'nombre' => 'AMAZONAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140252', 'canton' => '1402', 'nombre' => 'BERMEJOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140253', 'canton' => '1402', 'nombre' => 'BOMBOÍZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140254', 'canton' => '1402', 'nombre' => 'CHIGÜINDA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140255', 'canton' => '1402', 'nombre' => 'EL ROSARIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140256', 'canton' => '1402', 'nombre' => 'NUEVA TARQUI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140257', 'canton' => '1402', 'nombre' => 'SAN MIGUEL DE CUYES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140258', 'canton' => '1402', 'nombre' => 'EL IDEAL', 'tipo' => 'RURAL'],

            // LIMÓN INDANZA - MORONA SANTIAGO
            ['codigo_inec' => '140350', 'canton' => '1403', 'nombre' => 'GENERAL LEONIDAS PLAZA GUTIÉRREZ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140351', 'canton' => '1403', 'nombre' => 'INDANZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140353', 'canton' => '1403', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140356', 'canton' => '1403', 'nombre' => 'SAN MIGUEL DE CONCHAY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140357', 'canton' => '1403', 'nombre' => 'SANTA SUSANA DE CHIVIAZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140358', 'canton' => '1403', 'nombre' => 'YUNGANZA', 'tipo' => 'RURAL'],

            // PALORA - MORONA SANTIAGO
            ['codigo_inec' => '140450', 'canton' => '1404', 'nombre' => 'PALORA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140451', 'canton' => '1404', 'nombre' => 'ARAPICOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140452', 'canton' => '1404', 'nombre' => 'CUMANDÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140454', 'canton' => '1404', 'nombre' => 'SANGAY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140455', 'canton' => '1404', 'nombre' => '16 DE AGOSTO', 'tipo' => 'RURAL'],

            // SANTIAGO - MORONA SANTIAGO
            ['codigo_inec' => '140550', 'canton' => '1405', 'nombre' => 'SANTIAGO DE MÉNDEZ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140551', 'canton' => '1405', 'nombre' => 'COPAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140552', 'canton' => '1405', 'nombre' => 'CHUPIANZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140553', 'canton' => '1405', 'nombre' => 'PATUCA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140554', 'canton' => '1405', 'nombre' => 'SAN LUIS DE EL ACHO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140556', 'canton' => '1405', 'nombre' => 'TAYUZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140557', 'canton' => '1405', 'nombre' => 'SAN FRANCISCO DE CHINIMBIMI', 'tipo' => 'RURAL'],

            // SUCÚA - MORONA SANTIAGO
            ['codigo_inec' => '140650', 'canton' => '1406', 'nombre' => 'SUCÚA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140651', 'canton' => '1406', 'nombre' => 'ASUNCIÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140652', 'canton' => '1406', 'nombre' => 'HUAMBI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140655', 'canton' => '1406', 'nombre' => 'SANTA MARIANITA DE JESÚS', 'tipo' => 'RURAL'],

            // HUAMBOYA - MORONA SANTIAGO
            ['codigo_inec' => '140750', 'canton' => '1407', 'nombre' => 'HUAMBOYA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140751', 'canton' => '1407', 'nombre' => 'CHIGUAZA', 'tipo' => 'RURAL'],

            // SAN JUAN BOSCO - MORONA SANTIAGO
            ['codigo_inec' => '140850', 'canton' => '1408', 'nombre' => 'SAN JUAN BOSCO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140851', 'canton' => '1408', 'nombre' => 'PAN DE AZÚCAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140852', 'canton' => '1408', 'nombre' => 'SAN CARLOS DE LIMÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140853', 'canton' => '1408', 'nombre' => 'SAN JACINTO DE WAKAMBEIS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140854', 'canton' => '1408', 'nombre' => 'SANTIAGO DE PANANZA', 'tipo' => 'RURAL'],

            // TAISHA - MORONA SANTIAGO
            ['codigo_inec' => '140950', 'canton' => '1409', 'nombre' => 'TAISHA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '140951', 'canton' => '1409', 'nombre' => 'HUASAGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140952', 'canton' => '1409', 'nombre' => 'MACUMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140953', 'canton' => '1409', 'nombre' => 'TUUTINENTSA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '140954', 'canton' => '1409', 'nombre' => 'PUMPUENTSA', 'tipo' => 'RURAL'],

            // LOGROÑO - MORONA SANTIAGO
            ['codigo_inec' => '141050', 'canton' => '1410', 'nombre' => 'LOGROÑO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '141051', 'canton' => '1410', 'nombre' => 'YAUPI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '141052', 'canton' => '1410', 'nombre' => 'SHIMPIS', 'tipo' => 'RURAL'],

            // PABLO SEXTO - MORONA SANTIAGO
            ['codigo_inec' => '141150', 'canton' => '1411', 'nombre' => 'PABLO SEXTO', 'tipo' => 'URBANA'],

            // TIWINTZA - MORONA SANTIAGO
            ['codigo_inec' => '141250', 'canton' => '1412', 'nombre' => 'SANTIAGO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '141251', 'canton' => '1412', 'nombre' => 'SAN JOSÉ DE MORONA', 'tipo' => 'RURAL'],

            // SEVILLA DON BOSCO - MORONA SANTIAGO
            ['codigo_inec' => '141350', 'canton' => '1413', 'nombre' => 'SEVILLA DON BOSCO', 'tipo' => 'URBANA'],

            // TENA - NAPO
            ['codigo_inec' => '150150', 'canton' => '1501', 'nombre' => 'TENA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '150151', 'canton' => '1501', 'nombre' => 'AHUANO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150153', 'canton' => '1501', 'nombre' => 'CHONTAPUNTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150154', 'canton' => '1501', 'nombre' => 'PANO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150155', 'canton' => '1501', 'nombre' => 'PUERTO MISAHUALLÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150156', 'canton' => '1501', 'nombre' => 'PUERTO NAPO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150157', 'canton' => '1501', 'nombre' => 'TÁLAG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150158', 'canton' => '1501', 'nombre' => 'SAN JUAN DE MUYUNA', 'tipo' => 'RURAL'],

            // ARCHIDONA - NAPO
            ['codigo_inec' => '150350', 'canton' => '1503', 'nombre' => 'ARCHIDONA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '150352', 'canton' => '1503', 'nombre' => 'COTUNDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150354', 'canton' => '1503', 'nombre' => 'SAN PABLO DE USHPAYACU', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150356', 'canton' => '1503', 'nombre' => 'HATUN SUMAKU', 'tipo' => 'RURAL'],

            // EL CHACO - NAPO
            ['codigo_inec' => '150450', 'canton' => '1504', 'nombre' => 'EL CHACO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '150451', 'canton' => '1504', 'nombre' => 'GONZALO DÍAZ DE PINEDA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150452', 'canton' => '1504', 'nombre' => 'LINARES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150453', 'canton' => '1504', 'nombre' => 'OYACACHI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150454', 'canton' => '1504', 'nombre' => 'SANTA ROSA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150455', 'canton' => '1504', 'nombre' => 'SARDINAS', 'tipo' => 'RURAL'],

            // QUIJOS - NAPO
            ['codigo_inec' => '150750', 'canton' => '1507', 'nombre' => 'BAEZA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '150751', 'canton' => '1507', 'nombre' => 'COSANGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150752', 'canton' => '1507', 'nombre' => 'CUYUJA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150753', 'canton' => '1507', 'nombre' => 'PAPALLACTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150754', 'canton' => '1507', 'nombre' => 'SAN FRANCISCO DE BORJA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '150756', 'canton' => '1507', 'nombre' => 'SUMACO', 'tipo' => 'RURAL'],

            // CARLOS JULIO AROSEMENA TOLA - NAPO
            ['codigo_inec' => '150950', 'canton' => '1509', 'nombre' => 'CARLOS JULIO AROSEMENA TOLA', 'tipo' => 'URBANA'],

            // PASTAZA - PASTAZA
            ['codigo_inec' => '160150', 'canton' => '1601', 'nombre' => 'PUYO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '160152', 'canton' => '1601', 'nombre' => 'CANELOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160154', 'canton' => '1601', 'nombre' => 'DIEZ DE AGOSTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160155', 'canton' => '1601', 'nombre' => 'FÁTIMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160156', 'canton' => '1601', 'nombre' => 'MONTALVO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160157', 'canton' => '1601', 'nombre' => 'POMONA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160158', 'canton' => '1601', 'nombre' => 'RÍO CORRIENTES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160159', 'canton' => '1601', 'nombre' => 'RÍO TIGRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160161', 'canton' => '1601', 'nombre' => 'SARAYACU', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160162', 'canton' => '1601', 'nombre' => 'SIMÓN BOLÍVAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160163', 'canton' => '1601', 'nombre' => 'TARQUI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160164', 'canton' => '1601', 'nombre' => 'TENIENTE HUGO ORTIZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160165', 'canton' => '1601', 'nombre' => 'VERACRUZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160166', 'canton' => '1601', 'nombre' => 'EL TRIUNFO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160167', 'canton' => '1601', 'nombre' => 'SHUAR PASTAZA', 'tipo' => 'RURAL'],

            // MERA - PASTAZA
            ['codigo_inec' => '160250', 'canton' => '1602', 'nombre' => 'MERA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '160251', 'canton' => '1602', 'nombre' => 'MADRE TIERRA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '160252', 'canton' => '1602', 'nombre' => 'SHELL', 'tipo' => 'RURAL'],

            // SANTA CLARA - PASTAZA
            ['codigo_inec' => '160350', 'canton' => '1603', 'nombre' => 'SANTA CLARA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '160351', 'canton' => '1603', 'nombre' => 'SAN JOSÉ', 'tipo' => 'RURAL'],

            // ARAJUNO - PASTAZA
            ['codigo_inec' => '160450', 'canton' => '1604', 'nombre' => 'ARAJUNO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '160451', 'canton' => '1604', 'nombre' => 'CURARAY', 'tipo' => 'RURAL'],

            // DISTRITO METROPOLITANO DE QUITO - PICHINCHA
            ['codigo_inec' => '170150', 'canton' => '1701', 'nombre' => 'QUITO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '170151', 'canton' => '1701', 'nombre' => 'ALANGASÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170152', 'canton' => '1701', 'nombre' => 'AMAGUAÑA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170153', 'canton' => '1701', 'nombre' => 'ATAHUALPA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170154', 'canton' => '1701', 'nombre' => 'CALACALÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170155', 'canton' => '1701', 'nombre' => 'CALDERÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170156', 'canton' => '1701', 'nombre' => 'CONOCOTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170157', 'canton' => '1701', 'nombre' => 'CUMBAYÁ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170158', 'canton' => '1701', 'nombre' => 'CHAVEZPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170159', 'canton' => '1701', 'nombre' => 'CHECA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170160', 'canton' => '1701', 'nombre' => 'EL QUINCHE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170161', 'canton' => '1701', 'nombre' => 'GUALEA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170162', 'canton' => '1701', 'nombre' => 'GUANGOPOLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170163', 'canton' => '1701', 'nombre' => 'GUAYLLABAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170164', 'canton' => '1701', 'nombre' => 'LA MERCED', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170165', 'canton' => '1701', 'nombre' => 'LLANO CHICO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170166', 'canton' => '1701', 'nombre' => 'LLOA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170168', 'canton' => '1701', 'nombre' => 'NANEGAL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170169', 'canton' => '1701', 'nombre' => 'NANEGALITO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170170', 'canton' => '1701', 'nombre' => 'NAYÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170171', 'canton' => '1701', 'nombre' => 'NONO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170172', 'canton' => '1701', 'nombre' => 'PACTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170174', 'canton' => '1701', 'nombre' => 'PERUCHO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170175', 'canton' => '1701', 'nombre' => 'PIFO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170176', 'canton' => '1701', 'nombre' => 'PÍNTAG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170177', 'canton' => '1701', 'nombre' => 'POMASQUI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170178', 'canton' => '1701', 'nombre' => 'PUÉLLARO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170179', 'canton' => '1701', 'nombre' => 'PUEMBO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170180', 'canton' => '1701', 'nombre' => 'SAN ANTONIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170181', 'canton' => '1701', 'nombre' => 'SAN JOSÉ DE MINAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170183', 'canton' => '1701', 'nombre' => 'TABABELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170184', 'canton' => '1701', 'nombre' => 'TUMBACO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170185', 'canton' => '1701', 'nombre' => 'YARUQUÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170186', 'canton' => '1701', 'nombre' => 'ZÁMBIZA', 'tipo' => 'RURAL'],

            // CAYAMBE - PICHINCHA
            ['codigo_inec' => '170250', 'canton' => '1702', 'nombre' => 'CAYAMBE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '170251', 'canton' => '1702', 'nombre' => 'ASCÁZUBI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170252', 'canton' => '1702', 'nombre' => 'CANGAHUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170253', 'canton' => '1702', 'nombre' => 'OLMEDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170254', 'canton' => '1702', 'nombre' => 'OTÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170255', 'canton' => '1702', 'nombre' => 'SANTA ROSA DE CUZUBAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170256', 'canton' => '1702', 'nombre' => 'SAN JOSÉ DE AYORA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170257', 'canton' => '1702', 'nombre' => 'JUAN MONTALVO', 'tipo' => 'RURAL'],

            // MEJÍA - PICHINCHA
            ['codigo_inec' => '170350', 'canton' => '1703', 'nombre' => 'MACHACHI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '170351', 'canton' => '1703', 'nombre' => 'ALOAG', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170352', 'canton' => '1703', 'nombre' => 'ALOASÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170353', 'canton' => '1703', 'nombre' => 'CUTUGLAHUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170354', 'canton' => '1703', 'nombre' => 'EL CHAUPI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170355', 'canton' => '1703', 'nombre' => 'MANUEL CORNEJO ASTORGA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170356', 'canton' => '1703', 'nombre' => 'TAMBILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170357', 'canton' => '1703', 'nombre' => 'UYUMBICHO', 'tipo' => 'RURAL'],

            // PEDRO MONCAYO - PICHINCHA
            ['codigo_inec' => '170450', 'canton' => '1704', 'nombre' => 'TABACUNDO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '170451', 'canton' => '1704', 'nombre' => 'LA ESPERANZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170452', 'canton' => '1704', 'nombre' => 'MALCHINGUÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170453', 'canton' => '1704', 'nombre' => 'TOCACHI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170454', 'canton' => '1704', 'nombre' => 'TUPIGACHI', 'tipo' => 'RURAL'],

            // RUMIÑAHUI - PICHINCHA
            ['codigo_inec' => '170550', 'canton' => '1705', 'nombre' => 'SANGOLQUÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '170551', 'canton' => '1705', 'nombre' => 'COTOGCHOA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '170552', 'canton' => '1705', 'nombre' => 'RUMIPAMBA', 'tipo' => 'RURAL'],

            // SAN MIGUEL DE LOS BANCOS - PICHINCHA
            ['codigo_inec' => '170750', 'canton' => '1707', 'nombre' => 'SAN MIGUEL DE LOS BANCOS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '170751', 'canton' => '1707', 'nombre' => 'MINDO', 'tipo' => 'RURAL'],

            // PEDRO VICENTE MALDONADO - PICHINCHA
            ['codigo_inec' => '170850', 'canton' => '1708', 'nombre' => 'PEDRO VICENTE MALDONADO', 'tipo' => 'URBANA'],

            // PUERTO QUITO - PICHINCHA
            ['codigo_inec' => '170950', 'canton' => '1709', 'nombre' => 'PUERTO QUITO', 'tipo' => 'URBANA'],

            // AMBATO - TUNGURAHUA
            ['codigo_inec' => '180150', 'canton' => '1801', 'nombre' => 'AMBATO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180151', 'canton' => '1801', 'nombre' => 'AMBATILLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180152', 'canton' => '1801', 'nombre' => 'ATAHUALPA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180153', 'canton' => '1801', 'nombre' => 'AUGUSTO N. MARTÍNEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180154', 'canton' => '1801', 'nombre' => 'CONSTANTINO FERNÁNDEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180155', 'canton' => '1801', 'nombre' => 'HUACHI GRANDE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180156', 'canton' => '1801', 'nombre' => 'IZAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180157', 'canton' => '1801', 'nombre' => 'JUAN BENIGNO VELA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180158', 'canton' => '1801', 'nombre' => 'MONTALVO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180159', 'canton' => '1801', 'nombre' => 'PASA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180160', 'canton' => '1801', 'nombre' => 'PICAIHUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180161', 'canton' => '1801', 'nombre' => 'PILAGÜÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180162', 'canton' => '1801', 'nombre' => 'QUISAPINCHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180163', 'canton' => '1801', 'nombre' => 'SAN BARTOLOMÉ DE PINLLO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180164', 'canton' => '1801', 'nombre' => 'SAN FERNANDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180165', 'canton' => '1801', 'nombre' => 'SANTA ROSA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180166', 'canton' => '1801', 'nombre' => 'TOTORAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180167', 'canton' => '1801', 'nombre' => 'CUNCHIBAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180168', 'canton' => '1801', 'nombre' => 'UNAMUNCHO', 'tipo' => 'RURAL'],

            // BAÑOS DE AGUA SANTA - TUNGURAHUA
            ['codigo_inec' => '180250', 'canton' => '1802', 'nombre' => 'BAÑOS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180251', 'canton' => '1802', 'nombre' => 'LLIGUA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180252', 'canton' => '1802', 'nombre' => 'RÍO NEGRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180253', 'canton' => '1802', 'nombre' => 'RÍO VERDE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180254', 'canton' => '1802', 'nombre' => 'ULBA', 'tipo' => 'RURAL'],

            // CEVALLOS - TUNGURAHUA
            ['codigo_inec' => '180350', 'canton' => '1803', 'nombre' => 'CEVALLOS', 'tipo' => 'URBANA'],

            // MOCHA - TUNGURAHUA
            ['codigo_inec' => '180450', 'canton' => '1804', 'nombre' => 'MOCHA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180451', 'canton' => '1804', 'nombre' => 'PINGUILÍ', 'tipo' => 'RURAL'],

            // PATATE - TUNGURAHUA
            ['codigo_inec' => '180550', 'canton' => '1805', 'nombre' => 'PATATE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180551', 'canton' => '1805', 'nombre' => 'EL TRIUNFO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180552', 'canton' => '1805', 'nombre' => 'LOS ANDES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180553', 'canton' => '1805', 'nombre' => 'SUCRE', 'tipo' => 'RURAL'],

            // QUERO - TUNGURAHUA
            ['codigo_inec' => '180650', 'canton' => '1806', 'nombre' => 'QUERO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180651', 'canton' => '1806', 'nombre' => 'RUMIPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180652', 'canton' => '1806', 'nombre' => 'YANAYACU MOCHAPATA', 'tipo' => 'RURAL'],

            // SAN PEDRO DE PELILEO - TUNGURAHUA
            ['codigo_inec' => '180750', 'canton' => '1807', 'nombre' => 'PELILEO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180751', 'canton' => '1807', 'nombre' => 'BENÍTEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180752', 'canton' => '1807', 'nombre' => 'BOLÍVAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180753', 'canton' => '1807', 'nombre' => 'COTALÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180754', 'canton' => '1807', 'nombre' => 'CHIQUICHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180755', 'canton' => '1807', 'nombre' => 'EL ROSARIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180756', 'canton' => '1807', 'nombre' => 'GARCÍA MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180757', 'canton' => '1807', 'nombre' => 'GUAMBALÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180758', 'canton' => '1807', 'nombre' => 'SALASACA', 'tipo' => 'RURAL'],

            // SANTIAGO DE PÍLLARO - TUNGURAHUA
            ['codigo_inec' => '180850', 'canton' => '1808', 'nombre' => 'PÍLLARO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180851', 'canton' => '1808', 'nombre' => 'BAQUERIZO MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180852', 'canton' => '1808', 'nombre' => 'EMILIO MARÍA TERÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180853', 'canton' => '1808', 'nombre' => 'MARCOS ESPINEL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180854', 'canton' => '1808', 'nombre' => 'PRESIDENTE URBINA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180855', 'canton' => '1808', 'nombre' => 'SAN ANDRÉS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180856', 'canton' => '1808', 'nombre' => 'SAN JOSÉ DE POALÓ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '180857', 'canton' => '1808', 'nombre' => 'SAN MIGUELITO', 'tipo' => 'RURAL'],

            // TISALEO - TUNGURAHUA
            ['codigo_inec' => '180950', 'canton' => '1809', 'nombre' => 'TISALEO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '180951', 'canton' => '1809', 'nombre' => 'QUINCHICOTO', 'tipo' => 'RURAL'],

            // ZAMORA - ZAMORA CHINCHIPE
            ['codigo_inec' => '190150', 'canton' => '1901', 'nombre' => 'ZAMORA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190151', 'canton' => '1901', 'nombre' => 'CUMBARATZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190152', 'canton' => '1901', 'nombre' => 'GUADALUPE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190153', 'canton' => '1901', 'nombre' => 'IMBANA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190155', 'canton' => '1901', 'nombre' => 'SABANILLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190156', 'canton' => '1901', 'nombre' => 'TIMBARA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190158', 'canton' => '1901', 'nombre' => 'SAN CARLOS DE LAS MINAS', 'tipo' => 'RURAL'],

            // CHINCHIPE - ZAMORA CHINCHIPE
            ['codigo_inec' => '190250', 'canton' => '1902', 'nombre' => 'ZUMBA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190251', 'canton' => '1902', 'nombre' => 'CHITO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190252', 'canton' => '1902', 'nombre' => 'EL CHORRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190254', 'canton' => '1902', 'nombre' => 'LA CHONTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190256', 'canton' => '1902', 'nombre' => 'PUCAPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190259', 'canton' => '1902', 'nombre' => 'SAN ANDRÉS', 'tipo' => 'RURAL'],

            // NANGARITZA - ZAMORA CHINCHIPE
            ['codigo_inec' => '190350', 'canton' => '1903', 'nombre' => 'GUAYZIMI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190351', 'canton' => '1903', 'nombre' => 'ZURMI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190352', 'canton' => '1903', 'nombre' => 'NUEVO PARAÍSO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190353', 'canton' => '1903', 'nombre' => 'NANKAIS', 'tipo' => 'RURAL'],

            // YACUAMBI - ZAMORA CHINCHIPE
            ['codigo_inec' => '190450', 'canton' => '1904', 'nombre' => '28 DE MAYO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190451', 'canton' => '1904', 'nombre' => 'LA PAZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190452', 'canton' => '1904', 'nombre' => 'TUTUPALI', 'tipo' => 'RURAL'],

            // YANTZAZA - ZAMORA CHINCHIPE
            ['codigo_inec' => '190550', 'canton' => '1905', 'nombre' => 'YANTZAZA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190551', 'canton' => '1905', 'nombre' => 'CHICAÑA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190553', 'canton' => '1905', 'nombre' => 'LOS ENCUENTROS', 'tipo' => 'RURAL'],

            // EL PANGUI - ZAMORA CHINCHIPE
            ['codigo_inec' => '190650', 'canton' => '1906', 'nombre' => 'EL PANGUI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190651', 'canton' => '1906', 'nombre' => 'EL GUISME', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190652', 'canton' => '1906', 'nombre' => 'PACHICUTZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190653', 'canton' => '1906', 'nombre' => 'TUNDAYME', 'tipo' => 'RURAL'],

            // CENTINELA DEL CÓNDOR - ZAMORA CHINCHIPE
            ['codigo_inec' => '190750', 'canton' => '1907', 'nombre' => 'ZUMBI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190752', 'canton' => '1907', 'nombre' => 'TRIUNFO DORADO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190753', 'canton' => '1907', 'nombre' => 'PANGUINTZA', 'tipo' => 'RURAL'],

            // PALANDA - ZAMORA CHINCHIPE
            ['codigo_inec' => '190850', 'canton' => '1908', 'nombre' => 'PALANDA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190851', 'canton' => '1908', 'nombre' => 'EL PORVENIR DEL CARMEN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190852', 'canton' => '1908', 'nombre' => 'SAN FRANCISCO DEL VERGEL', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190853', 'canton' => '1908', 'nombre' => 'VALLADOLID', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190854', 'canton' => '1908', 'nombre' => 'LA CANELA', 'tipo' => 'RURAL'],

            // PAQUISHA - ZAMORA CHINCHIPE
            ['codigo_inec' => '190950', 'canton' => '1909', 'nombre' => 'PAQUISHA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '190951', 'canton' => '1909', 'nombre' => 'BELLAVISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '190952', 'canton' => '1909', 'nombre' => 'NUEVO QUITO', 'tipo' => 'RURAL'],

            // SAN CRISTÓBAL - GALÁPAGOS
            ['codigo_inec' => '200150', 'canton' => '2001', 'nombre' => 'PUERTO BAQUERIZO MORENO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '200151', 'canton' => '2001', 'nombre' => 'EL PROGRESO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '200152', 'canton' => '2001', 'nombre' => 'ISLA SANTA MARÍA FLOREANA', 'tipo' => 'RURAL'],

            // ISABELA - GALÁPAGOS
            ['codigo_inec' => '200250', 'canton' => '2002', 'nombre' => 'PUERTO VILLAMIL', 'tipo' => 'URBANA'],
            ['codigo_inec' => '200251', 'canton' => '2002', 'nombre' => 'TOMÁS DE BERLANGA', 'tipo' => 'RURAL'],

            // SANTA CRUZ - GALÁPAGOS
            ['codigo_inec' => '200350', 'canton' => '2003', 'nombre' => 'PUERTO AYORA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '200351', 'canton' => '2003', 'nombre' => 'BELLA VISTA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '200352', 'canton' => '2003', 'nombre' => 'SANTA ROSA', 'tipo' => 'RURAL'],

            // LAGO AGRIO - SUCUMBÍOS
            ['codigo_inec' => '210150', 'canton' => '2101', 'nombre' => 'NUEVA LOJA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210152', 'canton' => '2101', 'nombre' => 'DURENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210153', 'canton' => '2101', 'nombre' => 'GENERAL FARFÁN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210155', 'canton' => '2101', 'nombre' => 'EL ENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210156', 'canton' => '2101', 'nombre' => 'PACAYACU', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210157', 'canton' => '2101', 'nombre' => 'JAMBELÍ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210158', 'canton' => '2101', 'nombre' => 'SANTA CECILIA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210160', 'canton' => '2101', 'nombre' => '10 DE AGOSTO', 'tipo' => 'RURAL'],

            // GONZALO PIZARRO - SUCUMBÍOS
            ['codigo_inec' => '210250', 'canton' => '2102', 'nombre' => 'LUMBAQUÍ', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210251', 'canton' => '2102', 'nombre' => 'EL REVENTADOR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210252', 'canton' => '2102', 'nombre' => 'GONZALO PIZARRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210254', 'canton' => '2102', 'nombre' => 'PUERTO LIBRE', 'tipo' => 'RURAL'],

            // PUTUMAYO - SUCUMBÍOS
            ['codigo_inec' => '210350', 'canton' => '2103', 'nombre' => 'PUERTO EL CARMEN DE PUTUMAYO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210351', 'canton' => '2103', 'nombre' => 'PALMA ROJA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210352', 'canton' => '2103', 'nombre' => 'PUERTO BOLÍVAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210353', 'canton' => '2103', 'nombre' => 'PUERTO RODRÍGUEZ', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210354', 'canton' => '2103', 'nombre' => 'SANTA ELENA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210355', 'canton' => '2103', 'nombre' => 'SANSAHUARI', 'tipo' => 'RURAL'],

            // SHUSHUFINDI - SUCUMBÍOS
            ['codigo_inec' => '210450', 'canton' => '2104', 'nombre' => 'SHUSHUFINDI', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210451', 'canton' => '2104', 'nombre' => 'LIMONCOCHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210452', 'canton' => '2104', 'nombre' => 'PAÑACOCHA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210453', 'canton' => '2104', 'nombre' => 'SAN ROQUE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210454', 'canton' => '2104', 'nombre' => 'SAN PEDRO DE LOS COFÁNES', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210455', 'canton' => '2104', 'nombre' => 'SIETE DE JULIO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210456', 'canton' => '2104', 'nombre' => 'LA MAGDALENA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210457', 'canton' => '2104', 'nombre' => 'LA PRIMAVERA', 'tipo' => 'RURAL'],

            // SUCUMBÍOS - SUCUMBÍOS
            ['codigo_inec' => '210550', 'canton' => '2105', 'nombre' => 'LA BONITA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210551', 'canton' => '2105', 'nombre' => 'EL PLAYÓN DE SAN FRANCISCO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210552', 'canton' => '2105', 'nombre' => 'LA SOFÍA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210553', 'canton' => '2105', 'nombre' => 'ROSA FLORIDA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210554', 'canton' => '2105', 'nombre' => 'SANTA BÁRBARA', 'tipo' => 'RURAL'],

            // CASCALES - SUCUMBÍOS
            ['codigo_inec' => '210650', 'canton' => '2106', 'nombre' => 'EL DORADO DE CASCALES', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210651', 'canton' => '2106', 'nombre' => 'SANTA ROSA DE SUCUMBÍOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210652', 'canton' => '2106', 'nombre' => 'SEVILLA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210653', 'canton' => '2106', 'nombre' => 'NUEVA TRONCAL', 'tipo' => 'RURAL'],

            // CUYABENO - SUCUMBÍOS
            ['codigo_inec' => '210750', 'canton' => '2107', 'nombre' => 'TARAPOA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '210751', 'canton' => '2107', 'nombre' => 'CUYABENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '210752', 'canton' => '2107', 'nombre' => 'AGUAS NEGRAS', 'tipo' => 'RURAL'],

            // FRANCISCO DE ORELLANA - ORELLANA
            ['codigo_inec' => '220150', 'canton' => '2201', 'nombre' => 'EL COCA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '220151', 'canton' => '2201', 'nombre' => 'DAYUMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220152', 'canton' => '2201', 'nombre' => 'TARACOA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220153', 'canton' => '2201', 'nombre' => 'ALEJANDRO LABAKA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220154', 'canton' => '2201', 'nombre' => 'EL DORADO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220155', 'canton' => '2201', 'nombre' => 'EL EDÉN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220156', 'canton' => '2201', 'nombre' => 'GARCÍA MORENO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220157', 'canton' => '2201', 'nombre' => 'INÉS ARANGO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220158', 'canton' => '2201', 'nombre' => 'LA BELLEZA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220159', 'canton' => '2201', 'nombre' => 'NUEVO PARAÍSO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220160', 'canton' => '2201', 'nombre' => 'SAN JOSÉ DE GUAYUSA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220161', 'canton' => '2201', 'nombre' => 'SAN LUIS DE ARMENIA', 'tipo' => 'RURAL'],

            // AGUARICO - ORELLANA
            ['codigo_inec' => '220250', 'canton' => '2202', 'nombre' => 'NUEVO ROCAFUERTE', 'tipo' => 'URBANA'],
            ['codigo_inec' => '220251', 'canton' => '2202', 'nombre' => 'CAPITÁN AUGUSTO RIVADENEYRA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220252', 'canton' => '2202', 'nombre' => 'CONONACO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220253', 'canton' => '2202', 'nombre' => 'SANTA MARÍA DE HUIRIRIMA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220255', 'canton' => '2202', 'nombre' => 'YASUNÍ', 'tipo' => 'RURAL'],

            // LA JOYA DE LOS SACHAS - ORELLANA
            ['codigo_inec' => '220350', 'canton' => '2203', 'nombre' => 'LA JOYA DE LOS SACHAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '220351', 'canton' => '2203', 'nombre' => 'ENOKANQUI', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220352', 'canton' => '2203', 'nombre' => 'POMPEYA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220353', 'canton' => '2203', 'nombre' => 'SAN CARLOS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220354', 'canton' => '2203', 'nombre' => 'SAN SEBASTIÁN DEL COCA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220355', 'canton' => '2203', 'nombre' => 'LAGO SAN PEDRO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220356', 'canton' => '2203', 'nombre' => 'RUMIPAMBA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220357', 'canton' => '2203', 'nombre' => 'TRES DE NOVIEMBRE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220358', 'canton' => '2203', 'nombre' => 'UNIÓN MILAGREÑA', 'tipo' => 'RURAL'],

            // LORETO - ORELLANA
            ['codigo_inec' => '220450', 'canton' => '2204', 'nombre' => 'LORETO', 'tipo' => 'URBANA'],
            ['codigo_inec' => '220451', 'canton' => '2204', 'nombre' => 'ÁVILA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220452', 'canton' => '2204', 'nombre' => 'PUERTO MURIALDO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220453', 'canton' => '2204', 'nombre' => 'SAN JOSÉ DE PAYAMINO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220454', 'canton' => '2204', 'nombre' => 'SAN JOSÉ DE DAHUANO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '220455', 'canton' => '2204', 'nombre' => 'SAN VICENTE DE HUATICOCHA', 'tipo' => 'RURAL'],

            // SANTO DOMINGO - SANTO DOMINGO DE LOS TSÁCHILAS
            ['codigo_inec' => '230150', 'canton' => '2301', 'nombre' => 'SANTO DOMINGO DE LOS COLORADOS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '230151', 'canton' => '2301', 'nombre' => 'ALLURIQUÍN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230152', 'canton' => '2301', 'nombre' => 'PUERTO LIMÓN', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230153', 'canton' => '2301', 'nombre' => 'LUZ DE AMÉRICA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230154', 'canton' => '2301', 'nombre' => 'SAN JACINTO DEL BÚA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230155', 'canton' => '2301', 'nombre' => 'VALLE HERMOSO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230156', 'canton' => '2301', 'nombre' => 'EL ESFUERZO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230157', 'canton' => '2301', 'nombre' => 'SANTA MARÍA DEL TOACHI', 'tipo' => 'RURAL'],

            // LA CONCORDIA - SANTO DOMINGO DE LOS TSÁCHILAS
            ['codigo_inec' => '230250', 'canton' => '2302', 'nombre' => 'LA CONCORDIA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '230251', 'canton' => '2302', 'nombre' => 'MONTERREY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230252', 'canton' => '2302', 'nombre' => 'LA VILLEGAS', 'tipo' => 'RURAL'],
            ['codigo_inec' => '230253', 'canton' => '2302', 'nombre' => 'PLAN PILOTO', 'tipo' => 'RURAL'],

            // SANTA ELENA - SANTA ELENA
            ['codigo_inec' => '240150', 'canton' => '2401', 'nombre' => 'SANTA ELENA', 'tipo' => 'URBANA'],
            ['codigo_inec' => '240151', 'canton' => '2401', 'nombre' => 'ATAHUALPA', 'tipo' => 'RURAL'],
            ['codigo_inec' => '240152', 'canton' => '2401', 'nombre' => 'COLONCHE', 'tipo' => 'RURAL'],
            ['codigo_inec' => '240153', 'canton' => '2401', 'nombre' => 'CHANDUY', 'tipo' => 'RURAL'],
            ['codigo_inec' => '240154', 'canton' => '2401', 'nombre' => 'MANGLARALTO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '240155', 'canton' => '2401', 'nombre' => 'SIMÓN BOLÍVAR', 'tipo' => 'RURAL'],
            ['codigo_inec' => '240156', 'canton' => '2401', 'nombre' => 'SAN JOSÉ DE ANCÓN', 'tipo' => 'RURAL'],

            // LA LIBERTAD - SANTA ELENA
            ['codigo_inec' => '240250', 'canton' => '2402', 'nombre' => 'LA LIBERTAD', 'tipo' => 'URBANA'],

            // SALINAS - SANTA ELENA
            ['codigo_inec' => '240350', 'canton' => '2403', 'nombre' => 'SALINAS', 'tipo' => 'URBANA'],
            ['codigo_inec' => '240351', 'canton' => '2403', 'nombre' => 'ANCONCITO', 'tipo' => 'RURAL'],
            ['codigo_inec' => '240352', 'canton' => '2403', 'nombre' => 'JOSÉ LUIS TAMAYO', 'tipo' => 'RURAL'],
        ];

        $data = array_map(
            fn (array $parroquia) => [
                'canton_id'   => $cantones[$parroquia['canton']],
                'codigo_inec' => $parroquia['codigo_inec'],
                'nombre'      => $parroquia['nombre'],
                'tipo'        => $parroquia['tipo'],
            ],
            $parroquias
        );

        Parroquia::upsert(
            $data,
            ['codigo_inec'],
            ['canton_id', 'nombre', 'tipo']
        );
    }
}
