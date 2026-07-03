<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;
use DateTime;
use App\Core\Functions;
use App\Core\AppManipularError;
use Override;

class ListaTratativa extends Model
{

    protected $funciton;
    protected $manipulador;
    ##[Override]
    public function __construct()
    {
        $this->funciton  = new Functions();
        $this->manipulador = new AppManipularError(__DIR__ . '/../error/error.txt');
        return parent::__construct();
    }


    public function lista_mobilidades()
    {
        $sql = "SELECT * FROM mobilidade";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getRelatorio_origim_list($mes)
    {


        $sql = "";
        $sql = "SELECT  
                crcid as n_nro,
                crcdatvct as Vencimento,
                crcdocger as Doc_Ger,crcvlr as valor,
                clicobtel as telefone,
                clicomctt as Contato_financeiro,
                upper(clissp) as Suspenso,
                clinomraz as cliente,
                cliid,
                array_to_string(array_agg(cast(venean as text)),', ') as vendedor,
                perfilcobtipo, 
                crcprepago FROM 
                cli INNER JOIN crc ON crccli = cliid 
                LEFT JOIN vencli ON vencli.venclicli = cli.cliid
                LEFT JOIN ven ON vencli.vencliven = ven.venid 
                LEFT JOIN perfilcob ON perfilcobid = cliperfilcobid
                where 
                crcfil = 1 and
                upper(crcbxd) = 'N' AND
                crcvlr > '0.00' AND
                crcprepago = false
                AND NOT EXISTS (
                SELECT 1 FROM public.crc_tratativas_movimentacao mov 
			    WHERE mov.crc_tratativas_crcid = crc.crcid) ";



        $filtros = [];
        $params = [];

        // Cenário A: Filtro por Mês/Ano (Caso não tenha o período completo)
        if (!empty($mes)) {
            $dados_mes = explode('/', $mes);
            if (count($dados_mes) == 3) {

                $filtros[] = " EXTRACT(DAY FROM crc.crcdatvct) < :dia";
                $filtros[] = " EXTRACT(MONTH FROM crc.crcdatvct) = :mes";
                $filtros[] = " EXTRACT(YEAR FROM crc.crcdatvct) = :ano";
                $params[':dia'] = (int)$dados_mes[0];
                $params[':mes'] = (int)$dados_mes[1];
                $params[':ano'] = (int)$dados_mes[2];
            }
        }
        // Se houver filtros, aplica-os à consulta
        if (!empty($filtros)) {
            $sql .= " AND " . implode(" AND ", $filtros);
        }

        $sql .= "  group by crcid ,clicobtel,clicomctt,clissp,clinomraz,cliid,venean,perfilcobtipo,crcprepago
                ORDER BY vencimento desc;";

        try {
            $sql = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $sql->bindValue($key, $value);
            }
            $sql->execute();

            $result = [];
            while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $row;
            }

            return $result;
        } catch (PDOException $e) {

            $this->manipulador->manipuladorDeErros(10, 'Erro na busca do getRelatorio_origim: ' . $e->getMessage(), __FILE__, __LINE__);
            echo "ERRO: " . $e->getMessage();
        }
    }

    public function lista_notification()
    {


        $sql = "";

        $sql = "SELECT  
            crc.crcid as N_Nro,               
            crc.crcdatvct as Vencimento,
            crc.crcdocger as Doc_Ger,
            crc.crcvlr as valor,
            crc.crcbancobrasil as nosso_numero_gopag, -- adicionado campo do nosso número para facilitar a identificação da cobrança
            SUBSTRING(crc.crcbarcode, 34, 7) as codigo_barras, -- adicionado campo do código de barras para facilitar a identificação da cobrança
            cli.clicobtel as telefone,
            cli.clicomctt as Contato_financeiro,
            upper(cli.clissp) as Suspenso,
            cli.clinomraz as cliente,
            cli.cliid,
            cli.clisspatm as supensao_automatica,
            array_to_string(array_agg(cast(ven.venean as text)),', ') as vendedor,
            perfilcob.perfilcobtipo, 
            crc.crcprepago,
            real_mov.crc_tratativas_crcid as movivementacao,
            real_mov.crc_tratativa_tipo_id as idTipo,
            real_mov.crc_tipo_acoes_id as idAcoes,
            real_mov.descricao_movimentacao as descricao_mov, 
            real_mov.ctr_interno as contratoResponsavel,
            tipo.tipo_tratativa as status,
            ac.acao_descricao as descricao_acao,             
            st.crc_tratativas_id as ultima_info,
            st.ultima_consulta,
            st.cod_status,
			st.p_contato
			
        FROM 
            cli cli 
        INNER JOIN crc crc ON crc.crccli = cli.cliid 
        LEFT JOIN vencli vencli ON vencli.venclicli = cli.cliid
        LEFT JOIN ven ven ON vencli.vencliven = ven.venid 
        LEFT JOIN perfilcob perfilcob ON perfilcob.perfilcobid = cli.cliperfilcobid
        INNER JOIN (
            SELECT *,
                ROW_NUMBER() OVER (PARTITION BY crc_tratativas_crcid ORDER BY id_crc_tratativas DESC) as rn_mov
            FROM public.crc_tratativas_movimentacao
        ) real_mov ON real_mov.crc_tratativas_crcid = crc.crcid AND real_mov.rn_mov = 1
        INNER JOIN public.crc_tratativa_tipo tipo ON tipo.id_crc_tratativa_tipo = real_mov.crc_tratativa_tipo_id
        INNER JOIN public.crc_tipo_acoes ac on ac.cod_acao = real_mov.crc_tipo_acoes_id
        INNER JOIN (
            SELECT 
                crc_tratativas_id,
                cod_status,
                data_cadastro as ultima_consulta,
				p_proximo_contato AS p_contato,
                ROW_NUMBER() OVER (PARTITION BY crc_tratativas_id ORDER BY data_cadastro DESC ) as rn 
            FROM 
                public.crc_tratativas_status
        ) st ON st.crc_tratativas_id = real_mov.id_crc_tratativas AND st.rn = 1
         WHERE 
            crc.crcfil = 1 AND
            upper(crc.crcbxd) = 'N' AND
            crc.crcvlr > 0.00 AND
            crc.crcprepago = false
            AND st.p_contato IS NOT NULL
            GROUP BY 
                crc.crcid, crc.crcdatvct, crc.crcdocger, crc.crcvlr, crc.crcbancobrasil,crc.crcbarcode,cli.clicobtel,
                cli.clicomctt, cli.clissp, cli.clinomraz, cli.cliid, cli.clisspatm ,perfilcob.perfilcobtipo,
                crc.crcprepago,
                real_mov.crc_tratativas_crcid,
                real_mov.crc_tratativa_tipo_id,
                real_mov.crc_tipo_acoes_id,
                real_mov.descricao_movimentacao,
                real_mov.ctr_interno,
                tipo.tipo_tratativa,
                ac.acao_descricao,
                st.crc_tratativas_id,
                st.ultima_consulta,
                st.cod_status,
				st.p_contato
                ORDER BY 
				Vencimento DESC,
                st.crc_tratativas_id DESC";
        try {

            $constul = $this->db->prepare($sql);

            $constul->execute();

            if ($constul->rowCount() > 0) {
                $result = [];

                while ($items = $constul->fetch(PDO::FETCH_ASSOC)) {
                    // $items['perfilcobtipo'] = self::removerAcentos($items['perfilcobtipo']);


                    if ($items['n_nro'] == 700989 || $items['n_nro'] == 700988) {
                        $items['supensao_automatica'] = 'N';
                    }

                    if (isset($items['contratoresponsavel']) && !empty($items['contratoresponsavel'])) {
                        //PEGO O NOME DO USUARIOS RESPONSAVEL PELA INSERÇÃO DO DADO   
                        $items['res'] = self::info_responsavel($items['contratoresponsavel']);
                        $items['descricao_mov'] = $this->funciton->convertEncode($items['descricao_mov']);
                        $items['descricao_acao'] = $this->funciton->convertEncode($items['descricao_acao']);
                        $items['perfilcobtipo'] = $this->funciton->convertEncode($items['perfilcobtipo']);
                    } else {
                        $items['res'] = 'SISTEMA';
                    }

                    $result[] = $items;
                }

                return $result;
            } else {

                return ['msg' => 'Nenhum resultado encontrado para os filtros informados.'];
            }
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro na busca do relatori: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }
    public function info_responsavel($contrato)
    {


        $sql = "";
        $sql = "SELECT ctrapl, ctremail FROM ctr where ctrid = :ctrid";


        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':ctrid', $contrato);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'nome' => trim($result['ctrapl']),
                'email' => trim($result['ctremail'])
            ];
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(8, 'Erro na busca do ctr: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }

    public function insert_notification($info_sistema = null, $tipo_acoes, $ctr_interno, $idCobranca, $p_contato)
    {


        $sql = "INSERT INTO public.crc_tratativas_movimentacao(
                    crc_tratativas_crcid, crc_tratativa_tipo_id, crc_tipo_acoes_id, descricao_movimentacao, ctr_interno)
                    VALUES (:cobranca, :crc_tratativa_tipo_id, :crc_tipo_acoes_id, :descricao_movimentacao, :ctr_interno)
                    RETURNING id_crc_tratativas;";

        $sqlStatus = "INSERT INTO public.crc_tratativas_status (crc_tratativas_id, cod_status, status_descricao, p_proximo_contato) 
                      VALUES (:crc_tratativas_id, :cod_status, :status_descricao, :p_proximo_contato);";

        try {

            $crc_tratativa_tipo_id = $tipo_acoes;
            $crc_tipo_acoes_id = $tipo_acoes;
            $descricao_movimentacao =  isset($info_sistema) && !empty($info_sistema) ? $this->funciton->convertToLatin1('AGUARDANDO O INÍCIO') : $this->funciton->convertToLatin1('ENVIADO NOTIFICAÇÃO VIA EMAIL');




            $cliIds = self::getRelatorioCobranca($idCobranca);
            $tpos =  self::tipo_tratativa(); //PEGO O TIOPO DA TRA


            $new_tipo = self::listTipoContrato($crc_tratativa_tipo_id);
            $new_acoes = self::listTipoAcoes($crc_tipo_acoes_id);
            $res =  isset($info_sistema) && !empty($info_sistema) ? $info_sistema : self::info_responsavel($ctr_interno);


            $ocorrencia = isset($info_sistema) && !empty($info_sistema) ? "INSERIDO SISTEMA - FATURA VENCIDA: - COBRANÇA nº: " . $cliIds[0]['n_nro'] . " - TIPO: " . $this->funciton->convertEncode($new_tipo[0]['tipo_tratativa']) . " - AÇÃO: " . $this->funciton->convertEncode($new_acoes[0]['acao_descricao']) . " - DESCRIÇÃO: AGUARDANDO O INÍCIO" : "INSERIDO SISTEMA - RESPONSAVEL: " . $res['nome'] . " - EMAIL: " . $res['email'] . " - COBRANÇA: " . $cliIds[0]['n_nro'] . " - TIPO: " . $this->funciton->convertEncode($new_tipo[0]['tipo_tratativa']) . " - AÇÃO: " . $this->funciton->convertEncode($new_acoes[0]['acao_descricao']) . " - DESCRIÇÃO: " . $descricao_movimentacao;



            $nome =   isset($info_sistema) && !empty($info_sistema) ? $this->funciton->convertToLatin1('INSERIDO SISTEMA - FATURA VENCIDA') : "INSERIDO SISTEMA - ENVIO DE E-MAIL:";
            $ocorrencia = $this->funciton->convertToLatin1($ocorrencia);


            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':cobranca', $idCobranca);
            $stmt->bindParam(':crc_tratativa_tipo_id', $crc_tratativa_tipo_id);
            $stmt->bindParam(':crc_tipo_acoes_id', $crc_tipo_acoes_id);
            $stmt->bindParam(':descricao_movimentacao', $descricao_movimentacao);
            $stmt->bindParam(':ctr_interno', $ctr_interno);

            $stmt->execute();

            // Recupera o ID gerado usando o FETCH do RETURNING (Postgres)
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $idTratativa = $resultado['id_crc_tratativas'];
            $cod_status = $tipo_acoes;  //# dados;
            $stmt = $this->db->prepare($sqlStatus);
            $stmt->bindParam(':crc_tratativas_id', $idTratativa);
            $stmt->bindParam(':cod_status', $cod_status);
            $stmt->bindParam(':status_descricao', $nome);
            $stmt->bindParam(':p_proximo_contato', $p_contato); //CRIO UMA NOVA LINHA INFORMADO QUE TEVE O INFO DE ENTRAR EM CONTATO COM A PESSOA

            if ($stmt->execute()) {


                self::registrarOcorrencia($cliIds[0]['cliid'], $tpos[0]['tpoid'], $ocorrencia, $nome);

                return ['status' => 'success', 'message' => 'Movimentação e Status inseridos com sucesso!'];
            }
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro ao inserir notificação: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }

    public function listTipoContrato($cod = null)
    {

        $parametro = [];

        if ($cod !== null) {
            $sql = "SELECT * FROM public.crc_tratativa_tipo WHERE cod_tipo_tratativa = :cod_tipo_tratativa";
            $parametro[':cod_tipo_tratativa'] = $cod;
        } else {
            $sql = "SELECT * FROM public.crc_tratativa_tipo WHERE cod_tipo_tratativa NOT IN (6,7)";
        }

        $sql .= " ORDER BY tipo_tratativa ASC;";

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($parametro as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro na busca public.crc_tratativa_tipo: ' . $e->getMessage(), __FILE__, __LINE__);

            echo "ERRO: " . $e->getMessage();
        }
    }
    public function listTipoAcoes($cod = null)
    {
        $parametro = [];

        if ($cod !== null) {
            // quando foi enviado um código, não aplica o filtro not in
            $sql = "SELECT * FROM public.crc_tipo_acoes WHERE cod_acao = :cod";
            $parametro[':cod'] = $cod;
        } else {
            $sql = "SELECT * FROM public.crc_tipo_acoes WHERE cod_acao NOT IN (6,7)";
        }

        $sql .= " order by acao_descricao asc;";

        try {
            $stmt = $this->db->prepare($sql);


            foreach ($parametro as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro na busca das açoes public.crc_tipo_acoes : ' . $e->getMessage(), __FILE__, __LINE__);

            echo "ERRO: " . $e->getMessage();
        }
    }
    public function getRelatorioCobranca($idCobranca)
    {


        $sql = "SELECT  
                crcid as N_Nro,
                crcdatvct as Vencimento,
                crcdocger as Doc_Ger,crcvlr as valor,
                clicobtel as telefone,
                clicomctt as Contato_financeiro,
                upper(clissp) as Suspenso,
                clinomraz as cliente,
                cliid,
                array_to_string(array_agg(cast(venean as text)),', ') as vendedor,
                perfilcobtipo, 
                crcprepago FROM 
                cli INNER JOIN crc ON crccli = cliid 
                LEFT JOIN vencli ON vencli.venclicli = cli.cliid
                LEFT JOIN ven ON vencli.vencliven = ven.venid 
                LEFT JOIN perfilcob ON perfilcobid = cliperfilcobid
                where 
                crcfil =  1 and
                upper(crcbxd) = 'N' AND
                crcvlr > '0.00' AND
                crcprepago = false ";


        $params = [];
        if ($idCobranca) {
            $sql .= " AND crcid = :crcid";
            $params[':crcid'] = $idCobranca;
        }

        $sql .= "  group by crcid ,clicobtel,clicomctt,clissp,clinomraz,cliid,venean,perfilcobtipo,crcprepago
                    ORDER BY vencimento asc;";

        try {
            $sql = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $sql->bindValue($key, $value);
            }
            $sql->execute();

            $result = [];
            while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $row;
            }

            return $result;
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro na busca do getRelatorio_origim: ' . $e->getMessage(), __FILE__, __LINE__);
            echo "ERRO: " . $e->getMessage();
        }
    }

    public function tipo_tratativa()
    {

        $sql = "SELECT tpoid FROM public.tpo WHERE tpodsc = trim('TRATATIVA COBRANCA');";

        try {
            $stmt = $this->db->prepare($sql);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(10, 'Erro ao listar tipo de contrato: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }
    public function registrarOcorrencia($cliId, $tpos, $descricao, $nome)
    {
        $sql = "INSERT INTO public.cliocr(
                     cliocrcli, cliocrtpo, cliocrant, cliocrrsp)
                    VALUES (:cliocrcli, :cliocrtpo, :cliocrant, :cliocrrsp);";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':cliocrcli', $cliId);
            $stmt->bindParam(':cliocrtpo', $tpos);
            $stmt->bindParam(':cliocrant', $descricao);
            $stmt->bindParam(':cliocrrsp', $nome);

            $stmt->execute();
        } catch (PDOException $e) {
            $this->manipulador->manipuladorDeErros(11, 'Erro ao registrar ocorrência public.cliocr: ' . $e->getMessage(), __FILE__, __LINE__);
            error_log('Erro ao registrar ocorrência: ' . $e->getMessage());
        }
    }
}
