using System.Data;
using Dapper;
using Npgsql;
using System.Text.Json.Serialization;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

Dapper.DefaultTypeMap.MatchNamesWithUnderscores = true;

builder.Services.AddSingleton<IDbConnectionFactory>(_ =>
{
    var cs = builder.Configuration.GetConnectionString("Default")
             ?? throw new InvalidOperationException("Missing ConnectionStrings:Default");
    return new NpgsqlConnectionFactory(cs);
});

// CORS: modo DEV (permite cualquier origen)
builder.Services.AddCors(opt =>
{
    opt.AddPolicy("dev", p =>
        p.WithOrigins("http://localhost:5173", "http://127.0.0.1:5173")
         .AllowAnyHeader()
         .AllowAnyMethod()
    // .AllowCredentials() // SOLO si usás cookies o auth basada en credenciales
    );
});

var app = builder.Build();

// CORS middleware
app.UseCors("dev");

// Swagger
app.UseSwagger();
app.UseSwaggerUI();

// Preflight global: responde a OPTIONS (por si acaso)
/*app.MapMethods("{*path}", new[] { "OPTIONS" }, () => Results.Ok())
   .RequireCors();*/

// Health
app.MapGet("/health", () => Results.Ok(new { ok = true, at = DateTimeOffset.UtcNow }));

// Agrupamos /api y obligamos CORS a todos los endpoints
var api = app.MapGroup("/api").RequireCors("dev");
api.MapMethods("{*path}", new[] { "OPTIONS" }, () => Results.Ok());

// Companies
api.MapGet("/companies", async (IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var rows = await db.QueryAsync<Company>("SELECT id, name FROM companies ORDER BY name;");
    return Results.Ok(rows);
});

// Star types
api.MapGet("/star-types", async (IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var rows = await db.QueryAsync<StarType>("SELECT id, code, name FROM star_types ORDER BY id;");
    return Results.Ok(rows);
});

// Challenges
api.MapGet("/challenges", async (IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var rows = await db.QueryAsync<Challenge>("SELECT id, name FROM challenges ORDER BY name;");
    return Results.Ok(rows);
});

api.MapPost("/challenges", async (CreateChallengeDto dto, IDbConnectionFactory dbf) =>
{
    if (string.IsNullOrWhiteSpace(dto.Name)) return Results.BadRequest("Name required");
    using var db = dbf.Create();
    var id = await db.ExecuteScalarAsync<int>(
        "INSERT INTO challenges(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
        new { Name = dto.Name.Trim() }
    );
    return Results.Ok(new { id });
});

api.MapPut("/challenges/{id:int}", async (int id, CreateChallengeDto dto, IDbConnectionFactory dbf) =>
{
    if (string.IsNullOrWhiteSpace(dto.Name)) return Results.BadRequest("Name required");
    using var db = dbf.Create();

    var affected = await db.ExecuteAsync(
        "UPDATE challenges SET name=@Name WHERE id=@Id;",
        new { Id = id, Name = dto.Name.Trim() }
    );

    return affected == 0 ? Results.NotFound() : Results.NoContent();
});

api.MapDelete("/challenges/{id:int}", async (int id, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    try
    {
        var affected = await db.ExecuteAsync("DELETE FROM challenges WHERE id=@Id;", new { Id = id });
        return affected == 0 ? Results.NotFound() : Results.NoContent();
    }
    catch (PostgresException ex) when (ex.SqlState == "23503")
    {
        // FK violation (challenge usado en star_awards)
        return Results.BadRequest("No se puede eliminar: el desafío está en uso por estrellas.");
    }
});



// Employees
api.MapGet("/employees", async (string? query, int? companyId, bool? activeOnly, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var sql = @"
        SELECT
            e.id                AS ""Id"",
            e.full_name         AS ""FullName"",
            e.company_id        AS ""CompanyId"",
            c.name              AS ""CompanyName"",
            e.is_active         AS ""IsActive"",
            (e.created_at AT TIME ZONE 'UTC') AS ""CreatedAt""
        FROM employees e
        JOIN companies c ON c.id = e.company_id
        WHERE 1=1
    ";

    var p = new DynamicParameters();

    if (companyId is not null)
    {
        sql += " AND e.company_id = @CompanyId";
        p.Add("CompanyId", companyId);
    }

    if (activeOnly == true)
    {
        sql += " AND e.is_active = TRUE";
    }

    if (!string.IsNullOrWhiteSpace(query))
    {
        sql += " AND e.full_name ILIKE @Q";
        p.Add("Q", $"%{query.Trim()}%");
    }

    sql += " ORDER BY e.full_name;";

    var rows = await db.QueryAsync<EmployeeRow>(sql, p);
    return Results.Ok(rows);
});

api.MapPost("/employees", async (CreateEmployeeDto dto, IDbConnectionFactory dbf) =>
{
    if (string.IsNullOrWhiteSpace(dto.FullName)) return Results.BadRequest("FullName required");
    if (dto.CompanyId <= 0) return Results.BadRequest("CompanyId required");

    using var db = dbf.Create();

    var sql = @"
        INSERT INTO employees(full_name, company_id, is_active)
        VALUES (@FullName, @CompanyId, COALESCE(@IsActive, TRUE))
        ON CONFLICT(company_id, full_name)
        DO UPDATE SET is_active = EXCLUDED.is_active
        RETURNING id;
    ";

    var id = await db.ExecuteScalarAsync<int>(sql, new
    {
        FullName = dto.FullName.Trim(),
        CompanyId = dto.CompanyId,
        IsActive = dto.IsActive
    });

    return Results.Ok(new { id });
});

api.MapPut("/employees/{id:int}", async (int id, UpdateEmployeeDto dto, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var sql = @"
        UPDATE employees
        SET full_name = COALESCE(@FullName, full_name),
            company_id = COALESCE(@CompanyId, company_id),
            is_active = COALESCE(@IsActive, is_active)
        WHERE id = @Id;
    ";
    var affected = await db.ExecuteAsync(sql, new
    {
        Id = id,
        FullName = string.IsNullOrWhiteSpace(dto.FullName) ? null : dto.FullName.Trim(),
        dto.CompanyId,
        dto.IsActive
    });
    return affected == 0 ? Results.NotFound() : Results.NoContent();
});

api.MapDelete("/employees/{id:int}", async (int id, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    try
    {
        var affected = await db.ExecuteAsync("DELETE FROM employees WHERE id=@Id;", new { Id = id });
        return affected == 0 ? Results.NotFound() : Results.NoContent();
    }
    catch (PostgresException ex) when (ex.SqlState == "23503")
    {
        return Results.BadRequest("No se puede eliminar: el funcionario tiene estrellas registradas.");
    }
});


// Star awards
api.MapPost("/star-awards", async (CreateStarAwardDto dto, IDbConnectionFactory dbf) =>
{
    if (dto.EmployeeId <= 0) return Results.BadRequest("EmployeeId required");
    if (string.IsNullOrWhiteSpace(dto.StarCode)) return Results.BadRequest("StarCode required");
    if (dto.AwardDate is null) return Results.BadRequest("AwardDate required");

    using var db = dbf.Create();
    using var tx = db.BeginTransaction();

    try
    {
        // star_type
        var starType = await db.QueryFirstOrDefaultAsync<(int Id, string Prefix)>(
            "SELECT id, sticker_prefix AS Prefix FROM star_types WHERE code=@Code;",
            new { Code = dto.StarCode.Trim().ToUpperInvariant() },
            tx
        );
        if (starType.Id == 0) return Results.BadRequest("Invalid StarCode");

        // challenge
        int? challengeId = dto.ChallengeId;
        if (challengeId is null && !string.IsNullOrWhiteSpace(dto.ChallengeName))
        {
            challengeId = await db.ExecuteScalarAsync<int>(
                "INSERT INTO challenges(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
                new { Name = dto.ChallengeName.Trim() },
                tx
            );
        }

        string? uniqueCode = null;

        // 1) Si viene UniqueCode manual: validar y tomarlo (lock)
        if (!string.IsNullOrWhiteSpace(dto.UniqueCode))
        {
            uniqueCode = dto.UniqueCode.Trim().ToUpperInvariant();

            // Lock row para evitar doble asignación simultánea
            var ok = await db.ExecuteScalarAsync<int>(@"
              SELECT 1
              FROM star_stickers
              WHERE code = @Code
                AND star_type_id = @StarTypeId
                AND is_used = false
              FOR UPDATE;
            ", new { Code = uniqueCode, StarTypeId = starType.Id }, tx);

            if (ok != 1)
                return Results.BadRequest("Código inválido o ya utilizado.");

            // Marcar como usado (award_id se setea después de insertar)
            await db.ExecuteAsync(@"
              UPDATE star_stickers
              SET is_used = true,
                  used_at = now()
              WHERE code = @Code;
            ", new { Code = uniqueCode }, tx);
        }
        else
        {
            // 2) Si NO viene manual: tomar el primer disponible (con SKIP LOCKED)
            uniqueCode = await db.ExecuteScalarAsync<string?>(@"
              WITH picked AS (
                SELECT code
                FROM star_stickers
                WHERE star_type_id = @StarTypeId
                  AND is_used = false
                ORDER BY num
                FOR UPDATE SKIP LOCKED
                LIMIT 1
              )
              UPDATE star_stickers s
              SET is_used = true,
                  used_at = now()
              FROM picked
              WHERE s.code = picked.code
              RETURNING s.code;
            ", new { StarTypeId = starType.Id }, tx);

            if (string.IsNullOrWhiteSpace(uniqueCode))
                return Results.BadRequest("No hay más códigos disponibles para este tipo (límite 9999).");
        }

        // Insert award
        var awardId = await db.ExecuteScalarAsync<int>(@"
          INSERT INTO star_awards(employee_id, star_type_id, award_date, challenge_id, note, unique_code)
          VALUES (@EmployeeId, @StarTypeId, @AwardDate::date, @ChallengeId, @Note, @UniqueCode)
          RETURNING id;
        ", new
        {
            dto.EmployeeId,
            StarTypeId = starType.Id,
            AwardDate = dto.AwardDate.Value.ToString("yyyy-MM-dd"),
            ChallengeId = challengeId,
            Note = string.IsNullOrWhiteSpace(dto.Note) ? null : dto.Note.Trim(),
            UniqueCode = uniqueCode
        }, tx);

        // Asociar award al sticker
        await db.ExecuteAsync(@"
          UPDATE star_stickers
          SET used_award_id = @AwardId
          WHERE code = @Code;
        ", new { AwardId = awardId, Code = uniqueCode }, tx);

        tx.Commit();
        return Results.Ok(new { id = awardId, uniqueCode });
    }
    catch
    {
        tx.Rollback();
        throw;
    }
});


api.MapGet("/star-awards", async (
    int? employeeId,
    int? companyId,
    string? starCode,
    string? uniqueCode,
    DateOnly? from,
    DateOnly? to,
    IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var sql = @"
        SELECT
            sa.id               AS ""Id"",
            sa.employee_id      AS ""EmployeeId"",
            e.full_name         AS ""FullName"",
            e.company_id        AS ""CompanyId"",
            c.name              AS ""CompanyName"",
            st.code             AS ""StarCode"",
            sa.award_date       AS ""AwardDate"",
            ch.name             AS ""ChallengeName"",
            sa.note             AS ""Note"",
            sa.unique_code      AS ""UniqueCode"",
            sa.created_at       AS ""CreatedAt""
        FROM star_awards sa
        JOIN employees e ON e.id = sa.employee_id
        JOIN companies c ON c.id = e.company_id
        JOIN star_types st ON st.id = sa.star_type_id
        LEFT JOIN challenges ch ON ch.id = sa.challenge_id
        WHERE 1=1
    ";
    var p = new DynamicParameters();

    if (employeeId is not null)
    {
        sql += " AND sa.employee_id = @EmployeeId";
        p.Add("EmployeeId", employeeId);
    }

    if (companyId is not null)
    {
        sql += " AND e.company_id = @CompanyId";
        p.Add("CompanyId", companyId);
    }

    if (!string.IsNullOrWhiteSpace(starCode))
    {
        sql += " AND st.code = @StarCode";
        p.Add("StarCode", starCode.Trim().ToUpperInvariant());
    }

    if (!string.IsNullOrWhiteSpace(uniqueCode))
    {
        sql += " AND sa.unique_code ILIKE @UQ";
        p.Add("UQ", $"{uniqueCode.Trim()}%");
    }

    if (from is not null)
    {
        sql += " AND sa.award_date >= @From";
        p.Add("From", from.Value.ToString("yyyy-MM-dd"));
    }

    if (to is not null)
    {
        sql += " AND sa.award_date <= @To";
        p.Add("To", to.Value.ToString("yyyy-MM-dd"));
    }

    sql += " ORDER BY sa.award_date DESC, sa.id DESC LIMIT 500;";

    var rows = await db.QueryAsync<StarAwardRow>(sql, p);
    return Results.Ok(rows);
});


api.MapPut("/star-awards/{id:int}/edit", async (int id, EditStarAwardDto dto, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    using var tx = db.BeginTransaction();

    try
    {
        // 0) Traer award actual
        var current = await db.QueryFirstOrDefaultAsync<(int StarTypeId, string UniqueCode)>(@"
          SELECT star_type_id AS StarTypeId, unique_code AS UniqueCode
          FROM star_awards
          WHERE id = @Id
          FOR UPDATE;
        ", new { Id = id }, tx);

        if (current.StarTypeId == 0) return Results.NotFound("Star award no existe.");

        // 1) Determinar nuevo starType (si no mandan StarCode, mantener)
        int newStarTypeId = current.StarTypeId;

        if (!string.IsNullOrWhiteSpace(dto.StarCode))
        {
            newStarTypeId = await db.ExecuteScalarAsync<int?>(@"
              SELECT id FROM star_types WHERE code=@Code;
            ", new { Code = dto.StarCode.Trim().ToUpperInvariant() }, tx) ?? 0;

            if (newStarTypeId == 0) return Results.BadRequest("StarCode inválido.");
        }

        // 2) Liberar sticker anterior (si existe)
        if (!string.IsNullOrWhiteSpace(current.UniqueCode))
        {
            await db.ExecuteAsync(@"
              UPDATE star_stickers
              SET is_used=false, used_at=null, used_award_id=null
              WHERE code=@Code;
            ", new { Code = current.UniqueCode }, tx);
        }

        // 3) Elegir nuevo código
        string? newCode = null;

        if (!string.IsNullOrWhiteSpace(dto.UniqueCode))
        {
            newCode = dto.UniqueCode.Trim().ToUpperInvariant();

            // Validar que exista y que sea del tipo correcto y disponible
            // Permitimos re-usar si era el mismo (pero ya lo liberamos arriba, así que igual debe pasar disponible)
            var ok = await db.ExecuteScalarAsync<int?>(@"
              SELECT 1
              FROM star_stickers
              WHERE code=@Code
                AND star_type_id=@StarTypeId
                AND is_used=false
              FOR UPDATE;
            ", new { Code = newCode, StarTypeId = newStarTypeId }, tx);

            if (ok != 1) return Results.BadRequest("Código inválido / ya usado / no corresponde al tipo.");

            await db.ExecuteAsync(@"
              UPDATE star_stickers
              SET is_used=true, used_at=now()
              WHERE code=@Code;
            ", new { Code = newCode }, tx);
        }
        else
        {
            // Auto-asignar el primer disponible
            newCode = await db.ExecuteScalarAsync<string?>(@"
              WITH picked AS (
                SELECT code
                FROM star_stickers
                WHERE star_type_id=@StarTypeId
                  AND is_used=false
                ORDER BY num
                FOR UPDATE SKIP LOCKED
                LIMIT 1
              )
              UPDATE star_stickers s
              SET is_used=true, used_at=now()
              FROM picked
              WHERE s.code=picked.code
              RETURNING s.code;
            ", new { StarTypeId = newStarTypeId }, tx);

            if (string.IsNullOrWhiteSpace(newCode))
                return Results.BadRequest("No hay códigos disponibles para ese tipo.");
        }

        // 4) Actualizar award
        await db.ExecuteAsync(@"
          UPDATE star_awards
          SET star_type_id=@StarTypeId,
              unique_code=@UniqueCode
          WHERE id=@Id;
        ", new { Id = id, StarTypeId = newStarTypeId, UniqueCode = newCode }, tx);

        // 5) Asociar award al sticker
        await db.ExecuteAsync(@"
          UPDATE star_stickers
          SET used_award_id=@AwardId
          WHERE code=@Code;
        ", new { AwardId = id, Code = newCode }, tx);

        tx.Commit();
        return Results.Ok(new { id, uniqueCode = newCode });
    }
    catch
    {
        tx.Rollback();
        throw;
    }
});


api.MapPut("/star-awards/{id:int}/code", async (int id, UpdateStarAwardCodeDto dto, IDbConnectionFactory dbf) =>
{
    if (string.IsNullOrWhiteSpace(dto.UniqueCode))
        return Results.BadRequest("UniqueCode required");

    using var db = dbf.Create();
    using var tx = db.BeginTransaction();

    try
    {
        // 1) Traer award + tipo + código actual
        var award = await db.QueryFirstOrDefaultAsync<(int StarTypeId, string OldCode)>(@"
          SELECT star_type_id AS StarTypeId, unique_code AS OldCode
          FROM star_awards
          WHERE id = @Id
          FOR UPDATE;
        ", new { Id = id }, tx);

        if (award.StarTypeId == 0)
            return Results.NotFound();

        var newCode = dto.UniqueCode.Trim().ToUpperInvariant();

        if (newCode == award.OldCode)
        {
            tx.Commit();
            return Results.Ok(new { id, uniqueCode = award.OldCode });
        }

        // 2) Validar que el nuevo sticker exista y esté disponible (y lockearlo)
        var ok = await db.ExecuteScalarAsync<int?>(@"
          SELECT 1
          FROM star_stickers
          WHERE code = @Code
            AND star_type_id = @StarTypeId
            AND is_used = false
          FOR UPDATE;
        ", new { Code = newCode, StarTypeId = award.StarTypeId }, tx);

        if (ok != 1)
            return Results.BadRequest("Código inválido o ya utilizado.");

        // 3) Liberar sticker viejo
        await db.ExecuteAsync(@"
          UPDATE star_stickers
          SET is_used = false,
              used_award_id = NULL,
              used_at = NULL
          WHERE code = @OldCode;
        ", new { OldCode = award.OldCode }, tx);

        // 4) Marcar nuevo como usado por este award
        await db.ExecuteAsync(@"
          UPDATE star_stickers
          SET is_used = true,
              used_award_id = @AwardId,
              used_at = now()
          WHERE code = @NewCode;
        ", new { AwardId = id, NewCode = newCode }, tx);

        // 5) Actualizar award.unique_code
        await db.ExecuteAsync(@"
          UPDATE star_awards
          SET unique_code = @NewCode
          WHERE id = @AwardId;
        ", new { AwardId = id, NewCode = newCode }, tx);

        tx.Commit();
        return Results.Ok(new { id, uniqueCode = newCode });
    }
    catch (PostgresException ex) when (ex.SqlState == "23505")
    {
        tx.Rollback();
        return Results.BadRequest("Ese código ya está asignado (UNIQUE).");
    }
    catch
    {
        tx.Rollback();
        throw;
    }
});


api.MapDelete("/star-awards/{id:int}", async (int id, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    using var tx = db.BeginTransaction();

    try
    {
        // 1) Traer el código del award (lock)
        var code = await db.ExecuteScalarAsync<string?>(@"
          SELECT unique_code
          FROM star_awards
          WHERE id=@Id
          FOR UPDATE;
        ", new { Id = id }, tx);

        if (code is null) return Results.NotFound();

        // 2) Liberar sticker SOLO si ese código existe en star_stickers
        //    y pertenece a este award (por used_award_id) o por code exacto.
        //    (Esto evita liberar un sticker de otro award por error.)
        var released = await db.ExecuteAsync(@"
          UPDATE star_stickers
          SET is_used = false,
              used_at = null,
              used_award_id = null
          WHERE (used_award_id = @Id)
             OR (code = @Code AND used_award_id IS NULL)
        ", new { Id = id, Code = code }, tx);

        // 3) Borrar award
        await db.ExecuteAsync(@"
          DELETE FROM star_awards
          WHERE id=@Id;
        ", new { Id = id }, tx);

        tx.Commit();
        return Results.NoContent();
    }
    catch
    {
        tx.Rollback();
        throw;
    }
});



// Stats
api.MapGet("/stats/employee/{employeeId:int}", async (int employeeId, DateOnly? from, DateOnly? to, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();

    var dateFilter = "";
    var p = new DynamicParameters(new { EmployeeId = employeeId });

    if (from is not null)
    {
        dateFilter += " AND sa.award_date >= @From";
        p.Add("From", from.Value.ToString("yyyy-MM-dd"));
    }
    if (to is not null)
    {
        dateFilter += " AND sa.award_date <= @To";
        p.Add("To", to.Value.ToString("yyyy-MM-dd"));
    }

    var totals = (await db.QueryAsync<StarCountRow>(
        $@"
        SELECT st.code as star_code, COUNT(*)::int as count
        FROM star_awards sa
        JOIN star_types st ON st.id = sa.star_type_id
        WHERE sa.employee_id = @EmployeeId {dateFilter}
        GROUP BY st.code
        ORDER BY st.code;
        ", p)).ToList();

    var totalAll = totals.Sum(x => x.Count);

    var info = await db.QueryFirstOrDefaultAsync<EmployeeRow>(
        @"
        SELECT
            e.id AS ""Id"",
            e.full_name AS ""FullName"",
            e.company_id AS ""CompanyId"",
            c.name AS ""CompanyName"",
            e.is_active AS ""IsActive"",
            (e.created_at AT TIME ZONE 'UTC') AS ""CreatedAt""
        FROM employees e
        JOIN companies c ON c.id = e.company_id
        WHERE e.id=@EmployeeId;
        ",
        new { EmployeeId = employeeId }
    );

    if (info is null) return Results.NotFound();

    return Results.Ok(new { employee = info, total = totalAll, byStar = totals });
});

api.MapGet("/stats/star-type/{starCode}", async (string starCode, int? companyId, DateOnly? from, DateOnly? to, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var p = new DynamicParameters(new { StarCode = starCode.Trim().ToUpperInvariant() });

    var sql = @"
      SELECT e.id as employee_id, e.full_name, c.name as company_name, COUNT(*)::int as count
      FROM star_awards sa
      JOIN star_types st ON st.id = sa.star_type_id
      JOIN employees e ON e.id = sa.employee_id
      JOIN companies c ON c.id = e.company_id
      WHERE st.code = @StarCode
    ";

    if (companyId is not null)
    {
        sql += " AND e.company_id = @CompanyId";
        p.Add("CompanyId", companyId);
    }
    if (from is not null)
    {
        sql += " AND sa.award_date >= @From";
        p.Add("From", from.Value.ToString("yyyy-MM-dd"));
    }
    if (to is not null)
    {
        sql += " AND sa.award_date <= @To";
        p.Add("To", to.Value.ToString("yyyy-MM-dd"));
    }

    sql += " GROUP BY e.id, e.full_name, c.name ORDER BY count DESC, e.full_name;";

    var rows = await db.QueryAsync(sql, p);
    return Results.Ok(rows);
});

api.MapGet("/star-codes", async (
    string starCode,
    bool? availableOnly,
    string? q,
    int? limit,
    IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();

    var st = await db.QueryFirstOrDefaultAsync<(int Id, string Prefix)>(
        "SELECT id, sticker_prefix AS Prefix FROM star_types WHERE code=@Code;",
        new { Code = starCode.Trim().ToUpperInvariant() }
    );
    if (st.Id == 0) return Results.BadRequest("Invalid starCode");

    var take = Math.Clamp(limit ?? 200, 1, 2000);

    var sql = @"
      SELECT code, is_used
      FROM star_stickers
      WHERE star_type_id = @StarTypeId
    ";

    var p = new DynamicParameters(new { StarTypeId = st.Id });

    if (availableOnly == true) sql += " AND is_used = false";

    if (!string.IsNullOrWhiteSpace(q))
    {
        // q puede ser 'F01' o '010' o 'FC02'
        sql += " AND code ILIKE @Q";
        p.Add("Q", $"{q.Trim()}%");
    }

    sql += " ORDER BY num LIMIT @Take;";
    p.Add("Take", take);

    var rows = await db.QueryAsync(sql, p);
    return Results.Ok(rows);
});


api.MapGet("/stats/ranking", async (int? companyId, DateOnly? from, DateOnly? to, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    var p = new DynamicParameters();

    var sql = @"
      SELECT e.id as employee_id, e.full_name, c.name as company_name, COUNT(*)::int as total
      FROM star_awards sa
      JOIN employees e ON e.id = sa.employee_id
      JOIN companies c ON c.id = e.company_id
      WHERE 1=1
    ";

    if (companyId is not null)
    {
        sql += " AND e.company_id = @CompanyId";
        p.Add("CompanyId", companyId);
    }
    if (from is not null)
    {
        sql += " AND sa.award_date >= @From";
        p.Add("From", from.Value.ToString("yyyy-MM-dd"));
    }
    if (to is not null)
    {
        sql += " AND sa.award_date <= @To";
        p.Add("To", to.Value.ToString("yyyy-MM-dd"));
    }

    sql += " GROUP BY e.id, e.full_name, c.name ORDER BY total DESC, e.full_name LIMIT 200;";

    var rows = await db.QueryAsync(sql, p);
    return Results.Ok(rows);
});

// Backup export/import (intercambio entre escritorio y WordPress)
api.MapGet("/backup/export", async (IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();

    var companies = (await db.QueryAsync<BackupCompany>(
        "SELECT name FROM companies ORDER BY name;"
    )).ToList();

    var starTypes = (await db.QueryAsync<BackupStarType>(
        "SELECT code, name, sticker_prefix AS StickerPrefix FROM star_types ORDER BY id;"
    )).ToList();

    var challenges = (await db.QueryAsync<BackupChallenge>(
        "SELECT name FROM challenges ORDER BY name;"
    )).ToList();

    var employees = (await db.QueryAsync<BackupEmployee>(@"
        SELECT e.full_name AS FullName, c.name AS CompanyName, e.is_active AS IsActive
        FROM employees e
        JOIN companies c ON c.id = e.company_id
        ORDER BY c.name, e.full_name;
    ")).ToList();

    var awards = (await db.QueryAsync<BackupStarAward>(@"
        SELECT
          c.name        AS CompanyName,
          e.full_name   AS EmployeeFullName,
          st.code       AS StarCode,
          sa.award_date AS AwardDate,
          ch.name       AS ChallengeName,
          sa.note       AS Note,
          sa.unique_code AS UniqueCode
        FROM star_awards sa
        JOIN employees e ON e.id = sa.employee_id
        JOIN companies c ON c.id = e.company_id
        JOIN star_types st ON st.id = sa.star_type_id
        LEFT JOIN challenges ch ON ch.id = sa.challenge_id
        ORDER BY sa.award_date, sa.id;
    ")).ToList();

    var stickers = (await db.QueryAsync<BackupSticker>(@"
        SELECT
          s.code AS Code,
          st.code AS StarCode,
          s.num AS Num,
          s.is_used AS IsUsed
        FROM star_stickers s
        JOIN star_types st ON st.id = s.star_type_id
        ORDER BY st.code, s.num;
    ")).ToList();

    var payload = new BackupPayload(
        Version: "estrellas-backup-v1",
        ExportedAt: DateTimeOffset.UtcNow,
        Source: "dotnet-api",
        Mode: "merge",
        Companies: companies,
        StarTypes: starTypes,
        Challenges: challenges,
        Employees: employees,
        StarAwards: awards,
        StarStickers: stickers
    );

    return Results.Ok(payload);
});

api.MapPost("/backup/import", async (BackupPayload payload, IDbConnectionFactory dbf) =>
{
    using var db = dbf.Create();
    using var tx = db.BeginTransaction();

    try
    {
        var mode = (payload.Mode ?? "merge").Trim().ToLowerInvariant();
        if (mode is not ("merge" or "replace"))
            return Results.BadRequest("Mode inválido. Use 'merge' o 'replace'.");

        if (mode == "replace")
        {
            await db.ExecuteAsync("DELETE FROM star_awards;", transaction: tx);
            await db.ExecuteAsync("UPDATE star_stickers SET is_used=false, used_at=NULL, used_award_id=NULL;", transaction: tx);
            await db.ExecuteAsync("DELETE FROM employees;", transaction: tx);
            await db.ExecuteAsync("DELETE FROM challenges;", transaction: tx);
            await db.ExecuteAsync("DELETE FROM companies;", transaction: tx);
        }

        var companyIds = (await db.QueryAsync<(int Id, string Name)>(
            "SELECT id, name FROM companies;", transaction: tx
        )).ToDictionary(x => x.Name.Trim().ToLowerInvariant(), x => x.Id);

        var starTypeIds = (await db.QueryAsync<(int Id, string Code)>(
            "SELECT id, code FROM star_types;", transaction: tx
        )).ToDictionary(x => x.Code.Trim().ToUpperInvariant(), x => x.Id);

        int importedCompanies = 0, importedEmployees = 0, importedAwards = 0;

        foreach (var c in payload.Companies ?? Enumerable.Empty<BackupCompany>())
        {
            var name = (c.Name ?? "").Trim();
            if (name == "") continue;
            var key = name.ToLowerInvariant();
            if (companyIds.ContainsKey(key)) continue;
            var id = await db.ExecuteScalarAsync<int>(
                "INSERT INTO companies(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
                new { Name = name }, tx
            );
            companyIds[key] = id;
            importedCompanies++;
        }

        foreach (var st in payload.StarTypes ?? Enumerable.Empty<BackupStarType>())
        {
            var code = (st.Code ?? "").Trim().ToUpperInvariant();
            var name = (st.Name ?? "").Trim();
            if (code == "" || name == "") continue;
            var prefix = (st.StickerPrefix ?? "").Trim().ToUpperInvariant();
            if (prefix == "") prefix = code.Substring(0, 1);

            var id = await db.ExecuteScalarAsync<int>(
                @"INSERT INTO star_types(code, name, sticker_prefix)
                  VALUES (@Code, @Name, @Prefix)
                  ON CONFLICT(code) DO UPDATE SET
                    name = EXCLUDED.name,
                    sticker_prefix = EXCLUDED.sticker_prefix
                  RETURNING id;",
                new { Code = code, Name = name, Prefix = prefix }, tx
            );
            starTypeIds[code] = id;
        }

        var challengeIds = (await db.QueryAsync<(int Id, string Name)>(
            "SELECT id, name FROM challenges;", transaction: tx
        )).ToDictionary(x => x.Name.Trim().ToLowerInvariant(), x => x.Id);

        foreach (var ch in payload.Challenges ?? Enumerable.Empty<BackupChallenge>())
        {
            var name = (ch.Name ?? "").Trim();
            if (name == "") continue;
            var key = name.ToLowerInvariant();
            if (challengeIds.ContainsKey(key)) continue;
            var id = await db.ExecuteScalarAsync<int>(
                "INSERT INTO challenges(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
                new { Name = name }, tx
            );
            challengeIds[key] = id;
        }

        foreach (var e in payload.Employees ?? Enumerable.Empty<BackupEmployee>())
        {
            var fullName = (e.FullName ?? "").Trim();
            var companyName = (e.CompanyName ?? "").Trim();
            if (fullName == "" || companyName == "") continue;

            var ckey = companyName.ToLowerInvariant();
            if (!companyIds.TryGetValue(ckey, out var companyId))
            {
                companyId = await db.ExecuteScalarAsync<int>(
                    "INSERT INTO companies(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
                    new { Name = companyName }, tx
                );
                companyIds[ckey] = companyId;
                importedCompanies++;
            }

            await db.ExecuteAsync(@"
                INSERT INTO employees(full_name, company_id, is_active)
                VALUES (@FullName, @CompanyId, @IsActive)
                ON CONFLICT(company_id, full_name)
                DO UPDATE SET is_active = EXCLUDED.is_active;
            ", new { FullName = fullName, CompanyId = companyId, IsActive = e.IsActive }, tx);
            importedEmployees++;
        }

        var employeeIds = (await db.QueryAsync<(int Id, string FullName, int CompanyId)>(
            "SELECT id, full_name AS FullName, company_id AS CompanyId FROM employees;",
            transaction: tx
        )).ToDictionary(
            x => $"{x.CompanyId}|{x.FullName.Trim().ToLowerInvariant()}",
            x => x.Id
        );

        foreach (var a in payload.StarAwards ?? Enumerable.Empty<BackupStarAward>())
        {
            var companyName = (a.CompanyName ?? "").Trim();
            var employeeName = (a.EmployeeFullName ?? "").Trim();
            var starCode = (a.StarCode ?? "").Trim().ToUpperInvariant();
            var uniqueCode = (a.UniqueCode ?? "").Trim().ToUpperInvariant();
            if (companyName == "" || employeeName == "" || starCode == "") continue;

            if (!starTypeIds.TryGetValue(starCode, out var starTypeId)) continue;

            var companyKey = companyName.ToLowerInvariant();
            if (!companyIds.TryGetValue(companyKey, out var companyId))
            {
                companyId = await db.ExecuteScalarAsync<int>(
                    "INSERT INTO companies(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
                    new { Name = companyName }, tx
                );
                companyIds[companyKey] = companyId;
                importedCompanies++;
            }

            var empKey = $"{companyId}|{employeeName.ToLowerInvariant()}";
            if (!employeeIds.TryGetValue(empKey, out var employeeId))
            {
                employeeId = await db.ExecuteScalarAsync<int>(@"
                    INSERT INTO employees(full_name, company_id, is_active)
                    VALUES (@FullName, @CompanyId, TRUE)
                    ON CONFLICT(company_id, full_name) DO UPDATE SET full_name=EXCLUDED.full_name
                    RETURNING id;
                ", new { FullName = employeeName, CompanyId = companyId }, tx);
                employeeIds[empKey] = employeeId;
                importedEmployees++;
            }

            int? challengeId = null;
            var challengeName = (a.ChallengeName ?? "").Trim();
            if (challengeName != "")
            {
                var chKey = challengeName.ToLowerInvariant();
                if (!challengeIds.TryGetValue(chKey, out var chId))
                {
                    chId = await db.ExecuteScalarAsync<int>(
                        "INSERT INTO challenges(name) VALUES (@Name) ON CONFLICT(name) DO UPDATE SET name=EXCLUDED.name RETURNING id;",
                        new { Name = challengeName }, tx
                    );
                    challengeIds[chKey] = chId;
                }
                challengeId = chId;
            }

            var awardDate = a.AwardDate.Date;

            var existingId = await db.ExecuteScalarAsync<int?>(@"
                SELECT id
                FROM star_awards
                WHERE employee_id=@EmployeeId
                  AND star_type_id=@StarTypeId
                  AND award_date=@AwardDate::date
                  AND COALESCE(unique_code, '') = @UniqueCode
                LIMIT 1;
            ", new
            {
                EmployeeId = employeeId,
                StarTypeId = starTypeId,
                AwardDate = awardDate.ToString("yyyy-MM-dd"),
                UniqueCode = uniqueCode
            }, tx);

            if (existingId is not null) continue;

            var awardId = await db.ExecuteScalarAsync<int>(@"
                INSERT INTO star_awards(employee_id, star_type_id, award_date, challenge_id, note, unique_code)
                VALUES (@EmployeeId, @StarTypeId, @AwardDate::date, @ChallengeId, @Note, @UniqueCode)
                RETURNING id;
            ", new
            {
                EmployeeId = employeeId,
                StarTypeId = starTypeId,
                AwardDate = awardDate.ToString("yyyy-MM-dd"),
                ChallengeId = challengeId,
                Note = string.IsNullOrWhiteSpace(a.Note) ? null : a.Note.Trim(),
                UniqueCode = uniqueCode
            }, tx);
            importedAwards++;

            if (!string.IsNullOrWhiteSpace(uniqueCode))
            {
                var numeric = new string(uniqueCode.Where(char.IsDigit).ToArray());
                var num = int.TryParse(numeric, out var parsed) ? parsed : 0;

                await db.ExecuteAsync(@"
                    INSERT INTO star_stickers(code, star_type_id, num, is_used, used_at, used_award_id)
                    VALUES (@Code, @StarTypeId, @Num, TRUE, now(), @AwardId)
                    ON CONFLICT(code) DO UPDATE SET
                        star_type_id = EXCLUDED.star_type_id,
                        num = CASE WHEN EXCLUDED.num > 0 THEN EXCLUDED.num ELSE star_stickers.num END,
                        is_used = TRUE,
                        used_at = now(),
                        used_award_id = EXCLUDED.used_award_id;
                ", new { Code = uniqueCode, StarTypeId = starTypeId, Num = num, AwardId = awardId }, tx);
            }
        }

        tx.Commit();
        return Results.Ok(new
        {
            ok = true,
            mode,
            importedCompanies,
            importedEmployees,
            importedAwards
        });
    }
    catch
    {
        tx.Rollback();
        throw;
    }
});

app.Run();

// Infrastructure + Models
public interface IDbConnectionFactory
{
    IDbConnection Create();
}

public sealed class NpgsqlConnectionFactory : IDbConnectionFactory
{
    private readonly string _cs;
    public NpgsqlConnectionFactory(string cs) => _cs = cs;

    public IDbConnection Create()
    {
        var c = new NpgsqlConnection(_cs);
        c.Open();
        return c;
    }
}

public sealed record Company(int Id, string Name);
public sealed record StarType(int Id, string Code, string Name);
public sealed record Challenge(int Id, string Name);
public sealed record UpdateStarAwardCodeDto(string UniqueCode);
public sealed record EditStarAwardDto(
  string? StarCode,
  string? UniqueCode
);


public sealed record EmployeeRow(
    int Id,
    string FullName,
    int CompanyId,
    string CompanyName,
    bool IsActive,
    DateTime CreatedAt
);


public sealed record StarAwardRow(
    int Id,
    int EmployeeId,
    string FullName,
    int CompanyId,
    string CompanyName,
    string StarCode,
    DateTime AwardDate,
    string? ChallengeName,
    string? Note,
    string UniqueCode,
    DateTime CreatedAt
);


public sealed record StarCountRow(string StarCode, int Count);


public sealed record CreateEmployeeDto(string FullName, int CompanyId, bool? IsActive);
public sealed record UpdateEmployeeDto(string? FullName, int? CompanyId, bool? IsActive);

public sealed record CreateChallengeDto(string Name);

public sealed record CreateStarAwardDto(
    int EmployeeId,
    string StarCode,
    DateOnly? AwardDate,
    int? ChallengeId,
    string? ChallengeName,
    string? Note,
    string? UniqueCode 
);

public sealed record BackupPayload(
    string Version,
    DateTimeOffset ExportedAt,
    string Source,
    string? Mode,
    List<BackupCompany> Companies,
    List<BackupStarType> StarTypes,
    List<BackupChallenge> Challenges,
    List<BackupEmployee> Employees,
    List<BackupStarAward> StarAwards,
    List<BackupSticker> StarStickers
);
public sealed record BackupCompany(string Name);
public sealed record BackupStarType(string Code, string Name, string StickerPrefix);
public sealed record BackupChallenge(string Name);
public sealed record BackupEmployee(string FullName, string CompanyName, bool IsActive);
public sealed record BackupStarAward(
    string CompanyName,
    string EmployeeFullName,
    string StarCode,
    DateTime AwardDate,
    string? ChallengeName,
    string? Note,
    string? UniqueCode
);
public sealed record BackupSticker(string Code, string StarCode, int Num, bool IsUsed);