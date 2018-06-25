unit snrmonthreads;

{$mode objfpc}{$H+}

interface

uses
  Classes, SysUtils, FileUtil, IniFiles, sqldb, db, ZConnection, ZDataset, eventlog, snmpsend,
  asn1util, fpexprpars, LclIntf;

type

  TSNRSensorState = (
    snrsError    = -4,  // Ошибочное состояние (неожиданный ответ сервера или ошибка службы мониторинга)
    snrsOffLine  = -2, // Не в сети
    snrsUnknown  = -1, // Состояние не определено
    snrsOnLine   =  0, // В сети
    snrsSignaled =  1  // В сети, "сигнальное" значение
  );


  { TSNRERDMon }

  TSNRERDMon = class(TThread)
  private
    FWorkers: TList; // Список рабочих потоков
    FQue: TStringList; // Очередь датчиков для проверки
    Logger: TEventLog;
    FIniFileName: string;
    FTraps: TThread;
  protected
    procedure Execute; override;
  public
    constructor Create(const AIniFileName: string; const ALogger: TEventLog = nil; const AWorkerCount: integer = 50);
    destructor Destroy; override;
    procedure FlushQue(SQLQuery: TZQuery);
    procedure CheckTraps(SQLQuery: TZQuery);
  end;


implementation

type

  { TTrapItem }

  TTrapItem = class
  private
    FHost: string;
    FOID: string;
    FValue: string;
    procedure SetHost(AValue: string);
    procedure SetOID(AValue: string);
    procedure SetValue(AValue: string);
  public
    property Host: string read FHost write SetHost;
    property Value: string read FValue write SetValue;
    property OID: string read FOID write SetOID;
  end;

  { TSNMPTrapThread }

  TSNMPTrapThread = class(TThread)
  private
    FTraps: TThreadList;
  protected
    procedure Execute; override;
  public
    constructor Create;
    destructor Destroy; override;
    property Traps: TThreadList read FTraps;
  end;

  { TSNRERDWorkerThread }

  TSNRERDWorkerThread = class(TThread)
  private
    FParams: TParams;
    FRealParams: TParams;
    FStartTick: int64;
  protected
    procedure Execute; override;
    procedure ResetToBadSate(const state: TSNRSensorState; const ErrorMessage: string);
    { Сброс значений при ошибке }
  public
    constructor Create(const CreateSuspended: boolean = false);
    destructor Destroy; override;
  end;

{ TTrapItem }

procedure TTrapItem.SetHost(AValue: string);
begin
  if FHost=AValue then Exit;
  FHost:=AValue;
end;

procedure TTrapItem.SetOID(AValue: string);
begin
  if FOID=AValue then Exit;
  FOID:=AValue;
end;

procedure TTrapItem.SetValue(AValue: string);
begin
  if FValue=AValue then Exit;
  FValue:=AValue;
end;

{ TSNMPTrapThread }

procedure TSNMPTrapThread.Execute;
var
  snmp: TSNMPSend;
  i: integer;
  Item: TTrapItem;
begin
  snmp := TSNMPSend.Create;
  try
    snmp.TargetPort := '162';
    while not Terminated do
    begin
      snmp.Reply.Clear;
      if snmp.RecvTrap then
      begin
        for i := 0 to snmp.Reply.MIBCount - 1 do
        begin
          Item := TTrapItem.Create;
          Item.Host := snmp.HostIP;
          Item.Value := snmp.Reply.MIBByIndex(i).Value;
          Item.OID := snmp.Reply.MIBByIndex(i).OID;
          FTraps.Add(Item);
        end;
      end;
    end;
  finally
    snmp.Free;
    Terminate;
  end;
end;

constructor TSNMPTrapThread.Create;
begin
  inherited Create(true);
  FTraps := TThreadList.Create;
  Suspended := false;
end;

destructor TSNMPTrapThread.Destroy;
begin
  Terminate;
  Suspended := false;
  Sleep(10);
  Suspended := false;
  WaitFor;
  FTraps.Free;
  inherited Destroy;
end;

{ TSNRERDWorkerThread }

procedure TSNRERDWorkerThread.Execute;
var
  snmp: TSNMPSend;
  i, k: integer;
  f: Double;
  d: TDateTime;
  expr: TFPExpressionParser;
  signaled: boolean;
  retrycnt: integer;
  l: TStringList;
begin
  snmp := TSNMPSend.Create;
  expr := TFPExpressionParser.Create(nil);
  l := TStringList.Create;
  try
    while not Terminated do
    begin
      if Assigned(FParams) then // Имеется элемент для обработки
      begin
        FStartTick := GetTickCount64;
        try
          with snmp do
          begin
            Query.Clear;
            Query.Version := FParams.ParamByName('snmp_version').AsInteger;
            Query.Flags := TV3Flags(FParams.ParamByName('snmp_V3Flags').AsInteger);
            Query.Community := FParams.ParamByName('snmp_comunity').AsString;
            Query.FlagReportable := True;
            Query.AuthMode := TV3Auth(FParams.ParamByName('snmp_V3Auth').AsInteger);
            Query.UserName := FParams.ParamByName('snmp_username').AsString;
            Query.Password := FParams.ParamByName('snmp_password').AsString;
            TargetHost := FParams.ParamByName('snmp_host').AsString;
            TargetPort := FParams.ParamByName('snmp_port').AsString;
            Timeout := FParams.ParamByName('timeout').AsInteger;
            retrycnt := FParams.ParamByName('max_retry_count').AsInteger;
            if retrycnt <= 0 then retrycnt := 1;
            Query.PDUType := PDUGetRequest;
            Query.MIBAdd(FParams.ParamByName('snmp_oid').AsString, '', ASN1_NULL);
            FRealParams.Clear;
            with FRealParams.Add as TParam do  // Обновление времени выполнения
            begin
              AsInteger := FParams.ParamByName('sensor_id').AsInteger;
              Name := 'sensor_id';
            end;
            Sock.CloseSocket;
            Reply.Clear;
            while retrycnt > 0 do
            if SendRequest then
            begin
              retrycnt := 0; // Удачная попытка получения значения
              expr.Identifiers.Clear;
              l.Clear;
              l.Text := FParams.ParamByName('exp_vars').AsString;
              for i := 0 to l.Count - 1 do
              begin
                try
                  expr.Identifiers.AddStringVariable('str_' + l.Names[i], l.ValueFromIndex[i]);
                  if TryStrToInt(l.ValueFromIndex[i], k) then
                    expr.Identifiers.AddIntegerVariable('int_' + l.Names[i], k);
                  if TryStrToFloat(l.ValueFromIndex[i], f) then
                    expr.Identifiers.AddFloatVariable('float_' + l.Names[i], f);
                  if TryStrToDateTime(l.ValueFromIndex[i], d) then
                    expr.Identifiers.AddDateTimeVariable('date_' + l.Names[i], d);
                except
                end;
              end;
              l.Clear;
              l.Text := FParams.ParamByName('exp_vars_def').AsString;
              for i := 0 to l.Count - 1 do
              begin
                try
                  if expr.Identifiers.IndexOfIdentifier('str_' + l.Names[i]) < 0 then
                    expr.Identifiers.AddStringVariable('str_' + l.Names[i], l.ValueFromIndex[i]);
                  if TryStrToInt(l.ValueFromIndex[i], k) then
                    if expr.Identifiers.IndexOfIdentifier('int_' + l.Names[i]) < 0 then expr.Identifiers.AddIntegerVariable('int_' + l.Names[i], k);
                  if TryStrToFloat(l.ValueFromIndex[i], f) then
                    if expr.Identifiers.IndexOfIdentifier('float_' + l.Names[i]) < 0 then expr.Identifiers.AddFloatVariable('float_' + l.Names[i], f);
                  if TryStrToDateTime(l.ValueFromIndex[i], d) then
                    if expr.Identifiers.IndexOfIdentifier('date_' + l.Names[i]) < 0 then expr.Identifiers.AddDateTimeVariable('date_' + l.Names[i], d);
                except
                end;
              end;
              for i := 0 to Reply.MIBCount - 1 do
              begin
                expr.Identifiers.AddStringVariable('str'+IntToStr(i), Reply.MIBByIndex(i).Value);
                if TryStrToInt(Reply.MIBByIndex(i).Value, k) then
                  expr.Identifiers.AddIntegerVariable('int'+IntToStr(i), k);
                if TryStrToFloat(Reply.MIBByIndex(i).Value, f) then
                  expr.Identifiers.AddFloatVariable('float'+IntToStr(i), f);
                if TryStrToDateTime(Reply.MIBByIndex(i).Value, d) then
                  expr.Identifiers.AddDateTimeVariable('date'+IntToStr(i), d);
              end;
              if Terminated then break;

              with FRealParams.Add as TParam do  // Обновление значения
              begin
                expr.Expression := FParams.ParamByName('value_expr').AsString;
                expr.Evaluate;
                case expr.ResultType of
                  rtBoolean: if expr.AsBoolean then AsString := '1' else AsString := '0';
                  rtInteger: AsString := IntToStr(expr.AsInteger);
                  rtFloat: AsString := FloatToStr(expr.AsFloat);
                  rtDateTime: AsString := DateTimeToStr(expr.AsDateTime);
                  rtString: AsString := expr.AsString;
                  else AsString := '';
                end;
                Name := 'value';
              end;

              with FRealParams.Add as TParam do  // Обновление числового значения
              begin
                expr.Expression := FParams.ParamByName('value_int_expr').AsString;
                expr.Evaluate;
                case expr.ResultType of
                  rtBoolean: if expr.AsBoolean then AsFloat := 1 else AsFloat := 0;
                  rtInteger: AsFloat := expr.AsInteger;
                  rtFloat: AsFloat := expr.AsFloat;
                  rtDateTime: AsFloat := expr.AsDateTime;
                  rtString: if TryStrToFloat(expr.AsString, f) then AsFloat := f else AsFloat := FParams.ParamByName('value_int_expr').AsFloat;
                  else AsFloat := FParams.ParamByName('value_int_expr').AsFloat;
                end;
                Name := 'int_value';
              end;

              with FRealParams.Add as TParam do  // Обновление значения
              begin
                i := round(FParams.ParamByName('int_value').AsFloat - FRealParams.ParamByName('int_value').AsFloat);
                if i < 0 then AsInteger := 1 else AsInteger := 0;
                Name := 'value_change_sig';
              end;

              with FRealParams.Add as TParam do  // Обновление состояния
              begin
                expr.Expression := FParams.ParamByName('state_expr').AsString;
                expr.Evaluate;
                case expr.ResultType of
                  rtBoolean: signaled := expr.AsBoolean;
                  rtInteger: signaled := expr.AsInteger <> 0;
                  rtFloat: signaled := expr.AsFloat <> 0;
                  rtDateTime: signaled := expr.AsDateTime <> 0;
                  rtString: signaled := expr.AsString <> '';
                  else signaled := false;
                end;
                if signaled then AsInteger := integer(snrsSignaled)
                            else AsInteger := integer(snrsOnLine);
                Name := 'state';
              end;

              with FRealParams.Add as TParam do  // Обновление времени выполнения
              begin
                AsString := 'Ok';
                Name := 'last_error';
              end;

            end else // Неудачная попытка отправки запроса
            begin
              dec(retrycnt);
              if retrycnt = 0 then
              begin
                if Sock.LastError <> 0 then ResetToBadSate(snrsOffLine, snmp.Sock.LastErrorDesc)
                else ResetToBadSate(snrsOffLine, 'SNMP protocol error ' + IntToStr(Query.ErrorStatus));
              end;
            end;
          end;
        except
          on e:Exception do
          begin
            ResetToBadSate(snrsError, e.Message);
            if Terminated then break;
          end;
        end;
        with FRealParams.Add as TParam do  // Обновление времени выполнения
        begin
          AsInteger := GetTickCount64 - FStartTick;
          Name := 'last_check_duration';
        end;
      end;
      if Terminated then Break
      else Suspended := true;
    end;
  finally
    snmp.Free;
    expr.Free;
    l.Free;
  end;
end;

procedure TSNRERDWorkerThread.ResetToBadSate(const state: TSNRSensorState;
  const ErrorMessage: string);
begin
  FRealParams.Clear;
  with FRealParams.Add as TParam do  // Обновление времени выполнения
  begin
    AsInteger := GetTickCount64 - FStartTick;
    Name := 'last_check_duration';
  end;
  with FRealParams.Add as TParam do // Сбрасываем int значение
  begin
    AsInteger := FParams.ParamByName('sensor_id').AsInteger;
    Name := 'sensor_id';
  end;
  with FRealParams.Add as TParam do // Сбрасываем raw значение
  begin
    AsString := '';
    Name := 'value';
  end;
  with FRealParams.Add as TParam do // Сбрасываем int значение
  begin
    AsInteger := FParams.ParamByName('error_int_value').AsInteger;
    Name := 'int_value';
  end;
  with FRealParams.Add as TParam do // Сбрасываем state
  begin
    AsInteger := integer(state);
    Name := 'state';
  end;
  with FRealParams.Add as TParam do  // Обновление значения
  begin
    AsInteger := 0;
    Name := 'value_change_sig';
  end;
  with FRealParams.Add as TParam do // Сообщение о ошибке
  begin
    AsString := ErrorMessage;
    Name := 'last_error';
  end;
end;

constructor TSNRERDWorkerThread.Create(const CreateSuspended: boolean);
begin
  inherited Create(CreateSuspended);
  FRealParams := TParams.Create;
end;

destructor TSNRERDWorkerThread.Destroy;
begin
  Terminate;
  Suspended := false;
  Sleep(10);
  Suspended := false;
  WaitFor;
  FRealParams.Free;
  if Assigned(FParams) then FParams.Free;
  inherited Destroy;
end;

{ TSNRERDMon }

procedure TSNRERDMon.Execute;

  procedure FieldsToParams(const Fields: TFields; const Params: TParams);
  var
    x: integer;
    Param: TParam;
    Names: TStringList;
  begin
    params.Clear;
    Names := TStringList.Create;
    try
      Fields.GetFieldNames(Names);
      for x := 0 to Names.Count - 1 do
      begin
        Param := (Params.Add as TParam);
        with Param do
        begin
          Name := Names[x];
          Value := Fields.FieldByName(Names[x]).Value;
          //DataType := Fields.FieldByName(Names[x]).DataType;
        end;
      end;
    finally
      Names.Free;
    end;
  end;

var
  i: integer;
  Params: TParams;
  ini: TIniFile;
  ZConnection: TZConnection;
  SQLQuery: TZQuery;
  RQuery: TZReadOnlyQuery;
  FailIsLogged: boolean;
  IniNotExist: boolean;
  SelectedIDs: TStringList;
begin
  SelectedIDs := TStringList.Create;
  ZConnection := TZConnection.Create(nil);
  SQLQuery := TZQuery.Create(ZConnection);
  SQLQuery.Connection := ZConnection;
  RQuery := TZReadOnlyQuery.Create(ZConnection);
  RQuery.Connection := ZConnection;
  FailIsLogged := false;
  IniNotExist := false;
  try
    while not Terminated do
    begin
      try
        if not FileExists(FIniFileName) and Assigned(Logger) and not IniNotExist then
        begin
          IniNotExist := true;
          Logger.Warning('File ' + FIniFileName + ' not exist. Use default settings');
        end;
        ini := TIniFile.Create(FIniFileName);
        try
          ZConnection.Protocol := 'mysql-5';
          ZConnection.HostName := ini.ReadString('Database', 'HostName', 'localhost');
          ZConnection.Port := ini.ReadInteger('Database', 'Port', 3306);
          ZConnection.Database := ini.ReadString('Database', 'DatabaseName', 'snr-mondb');
          ZConnection.User := ini.ReadString('Database', 'UserName', 'root');
          ZConnection.Password := ini.ReadString('Database', 'Password', '');
        finally
          ini.Free;
        end;
        if ZConnection.Connected then
          ZConnection.Disconnect;
        ZConnection.Connect;
        SQLQuery.SQL.Text := 'SET CHARACTER SET `utf8`';
        SQLQuery.ExecSQL;
        SQLQuery.SQL.Text := 'SET NAMES `utf8`';
        SQLQuery.ExecSQL;
        RQuery.SQL.Text := 'SELECT                                  ' +
                           '   tbl2.*,                              ' +
                           '   IF(ISNULL(tbl2.exp_vars_tmp) OR (TRIM(tbl2.exp_vars_tmp) = ""), tbl2.exp_vars_def, tbl2.exp_vars_tmp) AS exp_vars, ' +
                           '   snmp_username,                       ' +
                           '   snmp_password,                       ' +
                           '   snmp_comunity,                       ' +
                           '   snmp_host,                           ' +
                           '   snmp_port                            ' +
                           '                                        ' +
                           ' FROM (SELECT                           ' +
                           '   tbl1.*,                              ' +
                           '   snmp_version,                        ' +
                           '   snmp_oid,                            ' +
                           '   snmp_V3Flags,                        ' +
                           '   snmp_V3Auth,                         ' +
                           '   snmp_datatype,                       ' +
                           '   state_expr,                          ' +
                           '   value_expr,                          ' +
                           '   value_int_expr,                      ' +
                           '   error_int_value,                     ' +
                           '   exp_vars_def                         ' +
                           '                                        ' +
                           ' FROM (SELECT                           ' +
                           '   sensor_id,                           ' +
                           '   max_retry_count,                     ' +
                           '   timeout,                             ' +
                           '   int_value,                           ' +
                           '   state,                               ' +
                           '   class_id,                            ' +
                           '   device_id,                           ' +
                           '   exp_vars AS exp_vars_tmp             ' +
                           ' FROM sensors                           ' +
                           ' WHERE NOT in_work AND enablied AND     ' +
                           ' (force_update OR                       ' +
                           '   (                                    ' +
                           '      (DATE_ADD(last_check, INTERVAL check_interval SECOND) < CURRENT_TIMESTAMP) OR ' +
                           '     ((signaled_check_interval > 0) AND (DATE_ADD(last_check, INTERVAL signaled_check_interval SECOND) < CURRENT_TIMESTAMP) AND (state = 2)) ' +
                           '   )                                    ' +
                           ' )  ' +
                           ' ORDER BY last_check) AS tbl1 ' +
                           '   LEFT JOIN sensors_classes USING (class_id)) AS tbl2 ' +
                           '   LEFT JOIN devices USING (device_id);';
        RQuery.Open;
        FailIsLogged := false;
        if Assigned(Logger) then Logger.Info('Successfully connected to database %s.', [ZConnection.Database]);
      except
        on e:Exception do
          begin
            if not FailIsLogged and Assigned(Logger) then Logger.Error(e.Message);
            FailIsLogged := true;
          end;
      end;

      if ZConnection.Connected then
      begin
        // Обновляем состояния датчиков (по состоянию на последнее получение данных)
        SQLQuery.SQL.Text := 'UPDATE sensors SET state = -1, in_work = 0, force_update = 1;';
        try
          SQLQuery.ExecSQL;
        except
          on e:Exception do
            begin
              if not FailIsLogged and Assigned(Logger) then Logger.Error(e.Message);
            end;
        end;

        while ZConnection.Connected and not Terminated do
        begin
          CheckTraps(SQLQuery);
          FlushQue(SQLQuery);
          if Terminated then Break;
          try
            if Terminated then Break;
            RQuery.Refresh;
            try
              SelectedIDs.Clear;
              while not RQuery.EOF do
              begin
                SelectedIDs.Add(RQuery.FieldByName('sensor_id').AsString);
                i := FQue.IndexOf(RQuery.FieldByName('sensor_id').AsString);
                if i < 0 then
                begin
                  Params := TParams.Create();
                  FieldsToParams(RQuery.Fields, Params);
                  FQue.AddObject(RQuery.FieldByName('sensor_id').AsString, Params);
                end else FieldsToParams(RQuery.Fields, FQue.Objects[i] as TParams);
                RQuery.Next;
                if Terminated then Break;
              end;
            finally
              //RQuery.Close;
            end;
          except
            on e:Exception do
              begin
                if not FailIsLogged and Assigned(Logger) then Logger.Error(e.Message);
                break;
              end;
          end;
          if Terminated then Break;
          if SelectedIDs.Count > 0 then
          begin
            SQLQuery.SQL.Text := 'UPDATE sensors SET in_work = 1 WHERE sensor_id in (' + SelectedIDs.DelimitedText + ');';
            SQLQuery.ExecSQL;
          end;
          FlushQue(SQLQuery);
          if Terminated then Break;
          Sleep(1000);
          if Terminated then Break;
        end;
      end else Sleep(5000); // Пытаемся переподключиться через 5 секунд
    end;
  finally
    SQLQuery.Close;
    SQLQuery.Free;
    RQuery.Close;
    RQuery.Free;
    ZConnection.Free;
    SelectedIDs.Free;
    Terminate;
  end;
end;

constructor TSNRERDMon.Create(const AIniFileName: string;
  const ALogger: TEventLog; const AWorkerCount: integer);
var
  i: integer;
begin
  inherited Create(true);
  FIniFileName := AIniFileName;
  FQue := TStringList.Create;
  FQue.Sorted := True;
  FQue.Duplicates := dupError;
  Logger := ALogger;
  FWorkers := TList.Create;
  for i := 0 to AWorkerCount - 1 do FWorkers.Add(TSNRERDWorkerThread.Create(true));
  FTraps := TSNMPTrapThread.Create;
end;

destructor TSNRERDMon.Destroy;
var
  i: integer;
begin
  Terminate;
  Suspended := false;
  Sleep(10);
  Suspended := false;
  WaitFor;
  for i := 0 to FWorkers.Count - 1 do TThread(FWorkers[i]).Free;
  FQue.Free;
  FWorkers.Free;
  FTraps.Free;
  inherited Destroy;
end;

procedure TSNRERDMon.FlushQue(SQLQuery: TZQuery);
var
  i: integer;
  Worker: TSNRERDWorkerThread;

  procedure AddWork;
  begin
    if FQue.Count > 0 then // Имеются ожидающие обработки элементы
    begin
      Worker.FParams := FQue.Objects[0] as TParams;
      FQue.Delete(0);
      Worker.Suspended := false;
    end;
  end;

begin
  for i := 0 to FWorkers.Count - 1 do
  begin
    Worker := TSNRERDWorkerThread(FWorkers[i]);
    if Worker.Suspended then
    begin
      if Assigned(Worker.FParams) then // Обработка возвращенного значения
      begin
        try
          SQLQuery.SQL.Text := 'UPDATE sensors SET `value` = :value, int_value = :int_value, last_check = CURRENT_TIMESTAMP, force_update = 0, last_check_duration = :last_check_duration, state = :state, value_change_sig = :value_change_sig, last_error = :last_error, in_work = 0 WHERE sensor_id = :sensor_id;';
          SQLQuery.Params.Assign(Worker.FRealParams);
          SQLQuery.ExecSQL;

        except
          on e:Exception do
            if Assigned(Logger) then Logger.Error(e.Message);
        end;
        Worker.FParams.Free;
        Worker.FParams := nil;
        AddWork;
      end else AddWork;// Поток свободен
    end;
  end;
end;

procedure TSNRERDMon.CheckTraps(SQLQuery: TZQuery);
var
  i: integer;
  Trap: TTrapItem;
  l, l2: TList;
begin
  // Проверка трапов
  l := TList.Create;
  try
    l2 := (FTraps as TSNMPTrapThread).Traps.LockList;
    try
      for i := 0 to l2.Count - 1 do l.Add(l2[i]);
    finally
      l2.Clear;
      (FTraps as TSNMPTrapThread).Traps.UnLockList;
    end;
    SQLQuery.SQL.Text := 'INSERT INTO trap_log (changed, host, oid, value) VALUES (STR_TO_DATE(DATE_FORMAT(now(),"%Y%m%d%H%i"), "%Y%m%d%H%i"), :host, :oid, :value) ON DUPLICATE KEY UPDATE cnt = cnt + 1';
    while l.Count > 0 do
    begin
      Trap := TTrapItem(l.Extract(l[0]));
      try
        SQLQuery.Params.Clear;
        with SQLQuery.Params.Add as TParam do
        begin
          AsString := Trap.Host;
          Name := 'host';
        end;
        with SQLQuery.Params.Add as TParam do
        begin
          AsString := Trap.OID;
          Name := 'oid';
        end;
        with SQLQuery.Params.Add as TParam do
        begin
          AsString := Trap.Value;
          Name := 'value';
        end;
        SQLQuery.ExecSQL;
      except
        on e:Exception do
          if Assigned(Logger) then Logger.Error(e.Message);
      end;
      FreeAndNil(Trap);
    end;
  finally
    l.Free;
  end;
end;

end.

