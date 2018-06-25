program snrmon;

{$mode objfpc}{$H+}
{.$DEFINE DEBUG}
uses
  {$IFDEF UNIX}
  Cmem,
  cthreads,
  {$ENDIF}
  Classes, SysUtils, CustApp,
  snrmonthreads, monalertthreads, eventlog, zcore, laz_synapse
  { you can add units after this };

type

  { TSNRMONApplication }

  TSNRMONApplication = class(TCustomApplication)
  protected
    FMonThread: TSNRERDMon;
    Logger: TEventLog;
    procedure DoRun; override;
  public
    constructor Create(TheOwner: TComponent); override;
    destructor Destroy; override;
    procedure WriteHelp; virtual;
  end;

{ TSNRMONApplication }

procedure TSNRMONApplication.DoRun;
var
  ErrorMsg: String;
begin
  // quick check parameters
  ErrorMsg:=CheckOptions('h', 'help');
  if ErrorMsg<>'' then begin
    ShowException(Exception.Create(ErrorMsg));
    Terminate;
    Exit;
  end;

  // parse parameters
  if HasOption('h', 'help') then begin
    WriteHelp;
    Terminate;
    Exit;
  end;

  { add your program here }
  {$IFDEF UNIX}
  FMonThread := TSNRERDMon.Create('/etc/snrmon/config.ini', Logger, 500);
  {$ELSE}
  FMonThread := TSNRERDMon.Create(ChangeFileExt(Application.ExeName, '.ini'), Logger);
  {$ENDIF}
  FMonThread.Suspended := false;
  while not Terminated do
  begin
    sleep(1000);
  end;
  FMonThread.Free;

  // stop program loop
  Terminate;
end;

constructor TSNRMONApplication.Create(TheOwner: TComponent);
begin
  inherited Create(TheOwner);
  StopOnException:=True;
  Logger := TEventLog.Create(Self);
  Logger.LogType := ltFile;
  {$IFDEF UNIX}
  Logger.FileName := '/var/log/snrmon.log';
  {$ELSE}
  Logger.FileName := ChangeFileExt(Application.ExeName, '.log');
  {$ENDIF}
end;

destructor TSNRMONApplication.Destroy;
begin
  inherited Destroy;
end;

procedure TSNRMONApplication.WriteHelp;
begin
  { add your help code here }
  writeln('Usage: ', ExeName, ' -h');
end;

var
  Application: TSNRMONApplication;
begin
  {$IFDEF DEBUG}
  // Assuming your build mode sets -dDEBUG in Project Options/Other when defining -gh
  // This avoids interference when running a production/default build without -gh

  // Set up -gh output for the Leakview package:
  if FileExists('/home/zldo/heap.trc') then
    DeleteFile('/home/zldo/heap.trc');
  SetHeapTraceOutput('/home/zldo/heap.trc');
  {$ENDIF DEBUG}
  Application:=TSNRMONApplication.Create(nil);
  Application.Title:='snr-mon';
  Application.Run;
  Application.Free;
end.

