<?php

namespace Database\Seeders;

use App\Models\Admin\Ubicaciones\Canton;
use App\Models\Admin\Ubicaciones\Provincia;
use Illuminate\Database\Seeder;

class CantonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provincias = Provincia::pluck('id', 'codigo_inec');

        // Fuente: Clasificador Geografico Estadistico - Codigos DPA 2026 (INEC).
        // https://aplicaciones2.ecuadorencifras.gob.ec/SIN/descargas/cdpa2026.xlsx
        $cantones = [
            // AZUAY
            ['codigo_inec' => '0101', 'provincia' => '01', 'nombre' => 'CUENCA'],
            ['codigo_inec' => '0102', 'provincia' => '01', 'nombre' => 'GIRÓN'],
            ['codigo_inec' => '0103', 'provincia' => '01', 'nombre' => 'GUALACEO'],
            ['codigo_inec' => '0104', 'provincia' => '01', 'nombre' => 'NABÓN'],
            ['codigo_inec' => '0105', 'provincia' => '01', 'nombre' => 'PAUTE'],
            ['codigo_inec' => '0106', 'provincia' => '01', 'nombre' => 'PUCARÁ'],
            ['codigo_inec' => '0107', 'provincia' => '01', 'nombre' => 'SAN FERNANDO'],
            ['codigo_inec' => '0108', 'provincia' => '01', 'nombre' => 'SANTA ISABEL'],
            ['codigo_inec' => '0109', 'provincia' => '01', 'nombre' => 'SÍGSIG'],
            ['codigo_inec' => '0110', 'provincia' => '01', 'nombre' => 'OÑA'],
            ['codigo_inec' => '0111', 'provincia' => '01', 'nombre' => 'CHORDELEG'],
            ['codigo_inec' => '0112', 'provincia' => '01', 'nombre' => 'EL PAN'],
            ['codigo_inec' => '0113', 'provincia' => '01', 'nombre' => 'SEVILLA DE ORO'],
            ['codigo_inec' => '0114', 'provincia' => '01', 'nombre' => 'GUACHAPALA'],
            ['codigo_inec' => '0115', 'provincia' => '01', 'nombre' => 'CAMILO PONCE ENRÍQUEZ'],

            // BOLÍVAR
            ['codigo_inec' => '0201', 'provincia' => '02', 'nombre' => 'GUARANDA'],
            ['codigo_inec' => '0202', 'provincia' => '02', 'nombre' => 'CHILLANES'],
            ['codigo_inec' => '0203', 'provincia' => '02', 'nombre' => 'CHIMBO'],
            ['codigo_inec' => '0204', 'provincia' => '02', 'nombre' => 'ECHEANDÍA'],
            ['codigo_inec' => '0205', 'provincia' => '02', 'nombre' => 'SAN MIGUEL'],
            ['codigo_inec' => '0206', 'provincia' => '02', 'nombre' => 'CALUMA'],
            ['codigo_inec' => '0207', 'provincia' => '02', 'nombre' => 'LAS NAVES'],

            // CAÑAR
            ['codigo_inec' => '0301', 'provincia' => '03', 'nombre' => 'AZOGUES'],
            ['codigo_inec' => '0302', 'provincia' => '03', 'nombre' => 'BIBLIÁN'],
            ['codigo_inec' => '0303', 'provincia' => '03', 'nombre' => 'CAÑAR'],
            ['codigo_inec' => '0304', 'provincia' => '03', 'nombre' => 'LA TRONCAL'],
            ['codigo_inec' => '0305', 'provincia' => '03', 'nombre' => 'EL TAMBO'],
            ['codigo_inec' => '0306', 'provincia' => '03', 'nombre' => 'DÉLEG'],
            ['codigo_inec' => '0307', 'provincia' => '03', 'nombre' => 'SUSCAL'],

            // CARCHI
            ['codigo_inec' => '0401', 'provincia' => '04', 'nombre' => 'TULCÁN'],
            ['codigo_inec' => '0402', 'provincia' => '04', 'nombre' => 'BOLÍVAR'],
            ['codigo_inec' => '0403', 'provincia' => '04', 'nombre' => 'ESPEJO'],
            ['codigo_inec' => '0404', 'provincia' => '04', 'nombre' => 'MIRA'],
            ['codigo_inec' => '0405', 'provincia' => '04', 'nombre' => 'MONTÚFAR'],
            ['codigo_inec' => '0406', 'provincia' => '04', 'nombre' => 'SAN PEDRO DE HUACA'],

            // COTOPAXI
            ['codigo_inec' => '0501', 'provincia' => '05', 'nombre' => 'LATACUNGA'],
            ['codigo_inec' => '0502', 'provincia' => '05', 'nombre' => 'LA MANÁ'],
            ['codigo_inec' => '0503', 'provincia' => '05', 'nombre' => 'PANGUA'],
            ['codigo_inec' => '0504', 'provincia' => '05', 'nombre' => 'PUJILÍ'],
            ['codigo_inec' => '0505', 'provincia' => '05', 'nombre' => 'SALCEDO'],
            ['codigo_inec' => '0506', 'provincia' => '05', 'nombre' => 'SAQUISILÍ'],
            ['codigo_inec' => '0507', 'provincia' => '05', 'nombre' => 'SIGCHOS'],

            // CHIMBORAZO
            ['codigo_inec' => '0601', 'provincia' => '06', 'nombre' => 'RIOBAMBA'],
            ['codigo_inec' => '0602', 'provincia' => '06', 'nombre' => 'ALAUSÍ'],
            ['codigo_inec' => '0603', 'provincia' => '06', 'nombre' => 'COLTA'],
            ['codigo_inec' => '0604', 'provincia' => '06', 'nombre' => 'CHAMBO'],
            ['codigo_inec' => '0605', 'provincia' => '06', 'nombre' => 'CHUNCHI'],
            ['codigo_inec' => '0606', 'provincia' => '06', 'nombre' => 'GUAMOTE'],
            ['codigo_inec' => '0607', 'provincia' => '06', 'nombre' => 'GUANO'],
            ['codigo_inec' => '0608', 'provincia' => '06', 'nombre' => 'PALLATANGA'],
            ['codigo_inec' => '0609', 'provincia' => '06', 'nombre' => 'PENIPE'],
            ['codigo_inec' => '0610', 'provincia' => '06', 'nombre' => 'CUMANDÁ'],

            // EL ORO
            ['codigo_inec' => '0701', 'provincia' => '07', 'nombre' => 'MACHALA'],
            ['codigo_inec' => '0702', 'provincia' => '07', 'nombre' => 'ARENILLAS'],
            ['codigo_inec' => '0703', 'provincia' => '07', 'nombre' => 'ATAHUALPA'],
            ['codigo_inec' => '0704', 'provincia' => '07', 'nombre' => 'BALSAS'],
            ['codigo_inec' => '0705', 'provincia' => '07', 'nombre' => 'CHILLA'],
            ['codigo_inec' => '0706', 'provincia' => '07', 'nombre' => 'EL GUABO'],
            ['codigo_inec' => '0707', 'provincia' => '07', 'nombre' => 'HUAQUILLAS'],
            ['codigo_inec' => '0708', 'provincia' => '07', 'nombre' => 'MARCABELÍ'],
            ['codigo_inec' => '0709', 'provincia' => '07', 'nombre' => 'PASAJE'],
            ['codigo_inec' => '0710', 'provincia' => '07', 'nombre' => 'PIÑAS'],
            ['codigo_inec' => '0711', 'provincia' => '07', 'nombre' => 'PORTOVELO'],
            ['codigo_inec' => '0712', 'provincia' => '07', 'nombre' => 'SANTA ROSA'],
            ['codigo_inec' => '0713', 'provincia' => '07', 'nombre' => 'ZARUMA'],
            ['codigo_inec' => '0714', 'provincia' => '07', 'nombre' => 'LAS LAJAS'],

            // ESMERALDAS
            ['codigo_inec' => '0801', 'provincia' => '08', 'nombre' => 'ESMERALDAS'],
            ['codigo_inec' => '0802', 'provincia' => '08', 'nombre' => 'ELOY ALFARO'],
            ['codigo_inec' => '0803', 'provincia' => '08', 'nombre' => 'MUISNE'],
            ['codigo_inec' => '0804', 'provincia' => '08', 'nombre' => 'QUININDÉ'],
            ['codigo_inec' => '0805', 'provincia' => '08', 'nombre' => 'SAN LORENZO'],
            ['codigo_inec' => '0806', 'provincia' => '08', 'nombre' => 'ATACAMES'],
            ['codigo_inec' => '0807', 'provincia' => '08', 'nombre' => 'RIOVERDE'],

            // GUAYAS
            ['codigo_inec' => '0901', 'provincia' => '09', 'nombre' => 'GUAYAQUIL'],
            ['codigo_inec' => '0902', 'provincia' => '09', 'nombre' => 'ALFREDO BAQUERIZO MORENO (JUJÁN)'],
            ['codigo_inec' => '0903', 'provincia' => '09', 'nombre' => 'BALAO'],
            ['codigo_inec' => '0904', 'provincia' => '09', 'nombre' => 'BALZAR'],
            ['codigo_inec' => '0905', 'provincia' => '09', 'nombre' => 'COLIMES'],
            ['codigo_inec' => '0906', 'provincia' => '09', 'nombre' => 'DAULE'],
            ['codigo_inec' => '0907', 'provincia' => '09', 'nombre' => 'DURÁN'],
            ['codigo_inec' => '0908', 'provincia' => '09', 'nombre' => 'EL EMPALME'],
            ['codigo_inec' => '0909', 'provincia' => '09', 'nombre' => 'EL TRIUNFO'],
            ['codigo_inec' => '0910', 'provincia' => '09', 'nombre' => 'MILAGRO'],
            ['codigo_inec' => '0911', 'provincia' => '09', 'nombre' => 'NARANJAL'],
            ['codigo_inec' => '0912', 'provincia' => '09', 'nombre' => 'NARANJITO'],
            ['codigo_inec' => '0913', 'provincia' => '09', 'nombre' => 'PALESTINA'],
            ['codigo_inec' => '0914', 'provincia' => '09', 'nombre' => 'PEDRO CARBO'],
            ['codigo_inec' => '0916', 'provincia' => '09', 'nombre' => 'SAMBORONDÓN'],
            ['codigo_inec' => '0918', 'provincia' => '09', 'nombre' => 'SANTA LUCÍA'],
            ['codigo_inec' => '0919', 'provincia' => '09', 'nombre' => 'SALITRE'],
            ['codigo_inec' => '0920', 'provincia' => '09', 'nombre' => 'SAN JACINTO DE YAGUACHI'],
            ['codigo_inec' => '0921', 'provincia' => '09', 'nombre' => 'PLAYAS'],
            ['codigo_inec' => '0922', 'provincia' => '09', 'nombre' => 'SIMÓN BOLÍVAR'],
            ['codigo_inec' => '0923', 'provincia' => '09', 'nombre' => 'CORONEL MARCELINO MARIDUEÑA'],
            ['codigo_inec' => '0924', 'provincia' => '09', 'nombre' => 'LOMAS DE SARGENTILLO'],
            ['codigo_inec' => '0925', 'provincia' => '09', 'nombre' => 'NOBOL'],
            ['codigo_inec' => '0927', 'provincia' => '09', 'nombre' => 'GENERAL ANTONIO ELIZALDE'],
            ['codigo_inec' => '0928', 'provincia' => '09', 'nombre' => 'ISIDRO AYORA'],

            // IMBABURA
            ['codigo_inec' => '1001', 'provincia' => '10', 'nombre' => 'IBARRA'],
            ['codigo_inec' => '1002', 'provincia' => '10', 'nombre' => 'ANTONIO ANTE'],
            ['codigo_inec' => '1003', 'provincia' => '10', 'nombre' => 'COTACACHI'],
            ['codigo_inec' => '1004', 'provincia' => '10', 'nombre' => 'OTAVALO'],
            ['codigo_inec' => '1005', 'provincia' => '10', 'nombre' => 'PIMAMPIRO'],
            ['codigo_inec' => '1006', 'provincia' => '10', 'nombre' => 'SAN MIGUEL DE URCUQUÍ'],

            // LOJA
            ['codigo_inec' => '1101', 'provincia' => '11', 'nombre' => 'LOJA'],
            ['codigo_inec' => '1102', 'provincia' => '11', 'nombre' => 'CALVAS'],
            ['codigo_inec' => '1103', 'provincia' => '11', 'nombre' => 'CATAMAYO'],
            ['codigo_inec' => '1104', 'provincia' => '11', 'nombre' => 'CELICA'],
            ['codigo_inec' => '1105', 'provincia' => '11', 'nombre' => 'CHAGUARPAMBA'],
            ['codigo_inec' => '1106', 'provincia' => '11', 'nombre' => 'ESPÍNDOLA'],
            ['codigo_inec' => '1107', 'provincia' => '11', 'nombre' => 'GONZANAMÁ'],
            ['codigo_inec' => '1108', 'provincia' => '11', 'nombre' => 'MACARÁ'],
            ['codigo_inec' => '1109', 'provincia' => '11', 'nombre' => 'PALTAS'],
            ['codigo_inec' => '1110', 'provincia' => '11', 'nombre' => 'PUYANGO'],
            ['codigo_inec' => '1111', 'provincia' => '11', 'nombre' => 'SARAGURO'],
            ['codigo_inec' => '1112', 'provincia' => '11', 'nombre' => 'SOZORANGA'],
            ['codigo_inec' => '1113', 'provincia' => '11', 'nombre' => 'ZAPOTILLO'],
            ['codigo_inec' => '1114', 'provincia' => '11', 'nombre' => 'PINDAL'],
            ['codigo_inec' => '1115', 'provincia' => '11', 'nombre' => 'QUILANGA'],
            ['codigo_inec' => '1116', 'provincia' => '11', 'nombre' => 'OLMEDO'],

            // LOS RÍOS
            ['codigo_inec' => '1201', 'provincia' => '12', 'nombre' => 'BABAHOYO'],
            ['codigo_inec' => '1202', 'provincia' => '12', 'nombre' => 'BABA'],
            ['codigo_inec' => '1203', 'provincia' => '12', 'nombre' => 'MONTALVO'],
            ['codigo_inec' => '1204', 'provincia' => '12', 'nombre' => 'PUEBLOVIEJO'],
            ['codigo_inec' => '1205', 'provincia' => '12', 'nombre' => 'QUEVEDO'],
            ['codigo_inec' => '1206', 'provincia' => '12', 'nombre' => 'URDANETA'],
            ['codigo_inec' => '1207', 'provincia' => '12', 'nombre' => 'VENTANAS'],
            ['codigo_inec' => '1208', 'provincia' => '12', 'nombre' => 'VINCES'],
            ['codigo_inec' => '1209', 'provincia' => '12', 'nombre' => 'PALENQUE'],
            ['codigo_inec' => '1210', 'provincia' => '12', 'nombre' => 'BUENA FE'],
            ['codigo_inec' => '1211', 'provincia' => '12', 'nombre' => 'VALENCIA'],
            ['codigo_inec' => '1212', 'provincia' => '12', 'nombre' => 'MOCACHE'],
            ['codigo_inec' => '1213', 'provincia' => '12', 'nombre' => 'QUINSALOMA'],

            // MANABÍ
            ['codigo_inec' => '1301', 'provincia' => '13', 'nombre' => 'PORTOVIEJO'],
            ['codigo_inec' => '1302', 'provincia' => '13', 'nombre' => 'BOLÍVAR'],
            ['codigo_inec' => '1303', 'provincia' => '13', 'nombre' => 'CHONE'],
            ['codigo_inec' => '1304', 'provincia' => '13', 'nombre' => 'EL CARMEN'],
            ['codigo_inec' => '1305', 'provincia' => '13', 'nombre' => 'FLAVIO ALFARO'],
            ['codigo_inec' => '1306', 'provincia' => '13', 'nombre' => 'JIPIJAPA'],
            ['codigo_inec' => '1307', 'provincia' => '13', 'nombre' => 'JUNÍN'],
            ['codigo_inec' => '1308', 'provincia' => '13', 'nombre' => 'MANTA'],
            ['codigo_inec' => '1309', 'provincia' => '13', 'nombre' => 'MONTECRISTI'],
            ['codigo_inec' => '1310', 'provincia' => '13', 'nombre' => 'PAJÁN'],
            ['codigo_inec' => '1311', 'provincia' => '13', 'nombre' => 'PICHINCHA'],
            ['codigo_inec' => '1312', 'provincia' => '13', 'nombre' => 'ROCAFUERTE'],
            ['codigo_inec' => '1313', 'provincia' => '13', 'nombre' => 'SANTA ANA'],
            ['codigo_inec' => '1314', 'provincia' => '13', 'nombre' => 'SUCRE'],
            ['codigo_inec' => '1315', 'provincia' => '13', 'nombre' => 'TOSAGUA'],
            ['codigo_inec' => '1316', 'provincia' => '13', 'nombre' => '24 DE MAYO'],
            ['codigo_inec' => '1317', 'provincia' => '13', 'nombre' => 'PEDERNALES'],
            ['codigo_inec' => '1318', 'provincia' => '13', 'nombre' => 'OLMEDO'],
            ['codigo_inec' => '1319', 'provincia' => '13', 'nombre' => 'PUERTO LÓPEZ'],
            ['codigo_inec' => '1320', 'provincia' => '13', 'nombre' => 'JAMA'],
            ['codigo_inec' => '1321', 'provincia' => '13', 'nombre' => 'JARAMIJÓ'],
            ['codigo_inec' => '1322', 'provincia' => '13', 'nombre' => 'SAN VICENTE'],

            // MORONA SANTIAGO
            ['codigo_inec' => '1401', 'provincia' => '14', 'nombre' => 'MORONA'],
            ['codigo_inec' => '1402', 'provincia' => '14', 'nombre' => 'GUALAQUIZA'],
            ['codigo_inec' => '1403', 'provincia' => '14', 'nombre' => 'LIMÓN INDANZA'],
            ['codigo_inec' => '1404', 'provincia' => '14', 'nombre' => 'PALORA'],
            ['codigo_inec' => '1405', 'provincia' => '14', 'nombre' => 'SANTIAGO'],
            ['codigo_inec' => '1406', 'provincia' => '14', 'nombre' => 'SUCÚA'],
            ['codigo_inec' => '1407', 'provincia' => '14', 'nombre' => 'HUAMBOYA'],
            ['codigo_inec' => '1408', 'provincia' => '14', 'nombre' => 'SAN JUAN BOSCO'],
            ['codigo_inec' => '1409', 'provincia' => '14', 'nombre' => 'TAISHA'],
            ['codigo_inec' => '1410', 'provincia' => '14', 'nombre' => 'LOGROÑO'],
            ['codigo_inec' => '1411', 'provincia' => '14', 'nombre' => 'PABLO SEXTO'],
            ['codigo_inec' => '1412', 'provincia' => '14', 'nombre' => 'TIWINTZA'],
            ['codigo_inec' => '1413', 'provincia' => '14', 'nombre' => 'SEVILLA DON BOSCO'],

            // NAPO
            ['codigo_inec' => '1501', 'provincia' => '15', 'nombre' => 'TENA'],
            ['codigo_inec' => '1503', 'provincia' => '15', 'nombre' => 'ARCHIDONA'],
            ['codigo_inec' => '1504', 'provincia' => '15', 'nombre' => 'EL CHACO'],
            ['codigo_inec' => '1507', 'provincia' => '15', 'nombre' => 'QUIJOS'],
            ['codigo_inec' => '1509', 'provincia' => '15', 'nombre' => 'CARLOS JULIO AROSEMENA TOLA'],

            // PASTAZA
            ['codigo_inec' => '1601', 'provincia' => '16', 'nombre' => 'PASTAZA'],
            ['codigo_inec' => '1602', 'provincia' => '16', 'nombre' => 'MERA'],
            ['codigo_inec' => '1603', 'provincia' => '16', 'nombre' => 'SANTA CLARA'],
            ['codigo_inec' => '1604', 'provincia' => '16', 'nombre' => 'ARAJUNO'],

            // PICHINCHA
            ['codigo_inec' => '1701', 'provincia' => '17', 'nombre' => 'DISTRITO METROPOLITANO DE QUITO'],
            ['codigo_inec' => '1702', 'provincia' => '17', 'nombre' => 'CAYAMBE'],
            ['codigo_inec' => '1703', 'provincia' => '17', 'nombre' => 'MEJÍA'],
            ['codigo_inec' => '1704', 'provincia' => '17', 'nombre' => 'PEDRO MONCAYO'],
            ['codigo_inec' => '1705', 'provincia' => '17', 'nombre' => 'RUMIÑAHUI'],
            ['codigo_inec' => '1707', 'provincia' => '17', 'nombre' => 'SAN MIGUEL DE LOS BANCOS'],
            ['codigo_inec' => '1708', 'provincia' => '17', 'nombre' => 'PEDRO VICENTE MALDONADO'],
            ['codigo_inec' => '1709', 'provincia' => '17', 'nombre' => 'PUERTO QUITO'],

            // TUNGURAHUA
            ['codigo_inec' => '1801', 'provincia' => '18', 'nombre' => 'AMBATO'],
            ['codigo_inec' => '1802', 'provincia' => '18', 'nombre' => 'BAÑOS DE AGUA SANTA'],
            ['codigo_inec' => '1803', 'provincia' => '18', 'nombre' => 'CEVALLOS'],
            ['codigo_inec' => '1804', 'provincia' => '18', 'nombre' => 'MOCHA'],
            ['codigo_inec' => '1805', 'provincia' => '18', 'nombre' => 'PATATE'],
            ['codigo_inec' => '1806', 'provincia' => '18', 'nombre' => 'QUERO'],
            ['codigo_inec' => '1807', 'provincia' => '18', 'nombre' => 'SAN PEDRO DE PELILEO'],
            ['codigo_inec' => '1808', 'provincia' => '18', 'nombre' => 'SANTIAGO DE PÍLLARO'],
            ['codigo_inec' => '1809', 'provincia' => '18', 'nombre' => 'TISALEO'],

            // ZAMORA CHINCHIPE
            ['codigo_inec' => '1901', 'provincia' => '19', 'nombre' => 'ZAMORA'],
            ['codigo_inec' => '1902', 'provincia' => '19', 'nombre' => 'CHINCHIPE'],
            ['codigo_inec' => '1903', 'provincia' => '19', 'nombre' => 'NANGARITZA'],
            ['codigo_inec' => '1904', 'provincia' => '19', 'nombre' => 'YACUAMBI'],
            ['codigo_inec' => '1905', 'provincia' => '19', 'nombre' => 'YANTZAZA'],
            ['codigo_inec' => '1906', 'provincia' => '19', 'nombre' => 'EL PANGUI'],
            ['codigo_inec' => '1907', 'provincia' => '19', 'nombre' => 'CENTINELA DEL CÓNDOR'],
            ['codigo_inec' => '1908', 'provincia' => '19', 'nombre' => 'PALANDA'],
            ['codigo_inec' => '1909', 'provincia' => '19', 'nombre' => 'PAQUISHA'],

            // GALÁPAGOS
            ['codigo_inec' => '2001', 'provincia' => '20', 'nombre' => 'SAN CRISTÓBAL'],
            ['codigo_inec' => '2002', 'provincia' => '20', 'nombre' => 'ISABELA'],
            ['codigo_inec' => '2003', 'provincia' => '20', 'nombre' => 'SANTA CRUZ'],

            // SUCUMBÍOS
            ['codigo_inec' => '2101', 'provincia' => '21', 'nombre' => 'LAGO AGRIO'],
            ['codigo_inec' => '2102', 'provincia' => '21', 'nombre' => 'GONZALO PIZARRO'],
            ['codigo_inec' => '2103', 'provincia' => '21', 'nombre' => 'PUTUMAYO'],
            ['codigo_inec' => '2104', 'provincia' => '21', 'nombre' => 'SHUSHUFINDI'],
            ['codigo_inec' => '2105', 'provincia' => '21', 'nombre' => 'SUCUMBÍOS'],
            ['codigo_inec' => '2106', 'provincia' => '21', 'nombre' => 'CASCALES'],
            ['codigo_inec' => '2107', 'provincia' => '21', 'nombre' => 'CUYABENO'],

            // ORELLANA
            ['codigo_inec' => '2201', 'provincia' => '22', 'nombre' => 'FRANCISCO DE ORELLANA'],
            ['codigo_inec' => '2202', 'provincia' => '22', 'nombre' => 'AGUARICO'],
            ['codigo_inec' => '2203', 'provincia' => '22', 'nombre' => 'LA JOYA DE LOS SACHAS'],
            ['codigo_inec' => '2204', 'provincia' => '22', 'nombre' => 'LORETO'],

            // SANTO DOMINGO DE LOS TSÁCHILAS
            ['codigo_inec' => '2301', 'provincia' => '23', 'nombre' => 'SANTO DOMINGO'],
            ['codigo_inec' => '2302', 'provincia' => '23', 'nombre' => 'LA CONCORDIA'],

            // SANTA ELENA
            ['codigo_inec' => '2401', 'provincia' => '24', 'nombre' => 'SANTA ELENA'],
            ['codigo_inec' => '2402', 'provincia' => '24', 'nombre' => 'LA LIBERTAD'],
            ['codigo_inec' => '2403', 'provincia' => '24', 'nombre' => 'SALINAS'],
        ];

        $data = array_map(
            fn (array $canton) => [
                'provincia_id' => $provincias[$canton['provincia']],
                'codigo_inec'  => $canton['codigo_inec'],
                'nombre'       => $canton['nombre'],
            ],
            $cantones
        );

        Canton::upsert(
            $data,
            ['codigo_inec'],
            ['provincia_id', 'nombre']
        );
    }
}
