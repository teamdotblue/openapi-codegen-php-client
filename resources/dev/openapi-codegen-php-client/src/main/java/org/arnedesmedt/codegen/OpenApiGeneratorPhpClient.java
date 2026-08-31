package org.teamblue.codegen;

import io.swagger.v3.oas.models.OpenAPI;
import io.swagger.v3.oas.models.Operation;
import io.swagger.v3.oas.models.Components;
import io.swagger.v3.oas.models.media.ArraySchema;
import io.swagger.v3.oas.models.media.Content;
import io.swagger.v3.oas.models.media.ComposedSchema;
import io.swagger.v3.oas.models.media.Schema;
import io.swagger.v3.oas.models.media.MediaType;
import io.swagger.v3.oas.models.media.ObjectSchema;
import io.swagger.v3.oas.models.parameters.Parameter;
import io.swagger.v3.oas.models.parameters.QueryParameter;
import io.swagger.v3.oas.models.parameters.RequestBody;
import io.swagger.v3.oas.models.Paths;
import io.swagger.v3.oas.models.PathItem;
import io.swagger.v3.oas.models.PathItem.HttpMethod;
import io.swagger.v3.oas.models.responses.ApiResponses;
import io.swagger.v3.oas.models.responses.ApiResponse;

import java.util.*;
import java.util.stream.Collectors;
import java.util.function.Function;
import java.io.File;

import org.apache.commons.lang3.StringUtils;
import org.openapitools.codegen.*;
import org.openapitools.codegen.model.ModelMap;
import org.openapitools.codegen.model.ModelsMap;
import org.openapitools.codegen.model.OperationsMap;
import org.openapitools.codegen.utils.ModelUtils;
import org.openapitools.codegen.languages.PhpClientCodegen;
import org.openapitools.codegen.templating.mustache.EscapeChar;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import com.fasterxml.jackson.databind.node.ArrayNode;

public class OpenApiGeneratorPhpClient extends PhpClientCodegen implements CodegenConfig {
  public static final String GENERATOR_NAME         = "openapi-codegen-php-client";
  public static final String HELP_URL               = "helpUrl";
  public static final String COPYRIGHT              = "copyright";
  public static final String CLIENT_CLASS           = "clientClass";
  public static final String CLIENT_CLASS_QUALIFIER = "clientClassQualifier";
  public static final String CLIENTS_MODE           = "clients";

  // OpenAPI string `format` values that map onto a ready-made, zero-config
  // team-blue/php-value-objects base class (no generated method overrides needed).
  private static final Map<String, String> STRING_FORMAT_VALUE_OBJECTS = new HashMap<>();

  static {
    STRING_FORMAT_VALUE_OBJECTS.put("uuid", "TeamBlue\\ValueObjects\\Implementation\\String\\UuidValue");
    STRING_FORMAT_VALUE_OBJECTS.put("email", "TeamBlue\\ValueObjects\\Implementation\\String\\EmailValue");
    STRING_FORMAT_VALUE_OBJECTS.put("hostname", "TeamBlue\\ValueObjects\\Implementation\\String\\HostnameValue");
    STRING_FORMAT_VALUE_OBJECTS.put("uri", "TeamBlue\\ValueObjects\\Implementation\\String\\UriValue");
    STRING_FORMAT_VALUE_OBJECTS.put("url", "TeamBlue\\ValueObjects\\Implementation\\String\\UrlValue");
    STRING_FORMAT_VALUE_OBJECTS.put("ipv4", "TeamBlue\\ValueObjects\\Implementation\\String\\IpV4Value");
    STRING_FORMAT_VALUE_OBJECTS.put("ipv6", "TeamBlue\\ValueObjects\\Implementation\\String\\IpV6Value");
    STRING_FORMAT_VALUE_OBJECTS.put("byte", "TeamBlue\\ValueObjects\\Implementation\\String\\Base64EncodedStringValue");
    STRING_FORMAT_VALUE_OBJECTS.put("date-time", "TeamBlue\\ValueObjects\\Implementation\\String\\DateTimeValue");
    STRING_FORMAT_VALUE_OBJECTS.put("date", "TeamBlue\\ValueObjects\\Implementation\\String\\DateTimeValue");
    STRING_FORMAT_VALUE_OBJECTS.put("password", "TeamBlue\\ValueObjects\\Implementation\\String\\SensitiveValue");
  }

  private boolean clientsMode = false;

  public OpenApiGeneratorPhpClient() {
    super();

    cliOptions.add(new CliOption(HELP_URL, "Help URL"));
    cliOptions.add(new CliOption(COPYRIGHT, "Copyright"));
    cliOptions.add(new CliOption(CLIENT_CLASS, "Client file"));

    typeMapping.put("date", "\\TeamBlue\\\\ValueObjects\\\\Implementation\\\\String\\\\DateTimeValue");
    typeMapping.put("Date", "\\TeamBlue\\\\ValueObjects\\\\Implementation\\\\String\\\\DateTimeValue");
    typeMapping.put("DateTime", "\\TeamBlue\\\\ValueObjects\\\\Implementation\\\\String\\\\DateTimeValue");
    typeMapping.put("set", "array");
    typeMapping.put("object", "array");

    this.setTemplateDir(OpenApiGeneratorPhpClient.GENERATOR_NAME);
    this.embeddedTemplateDir = this.templateDir();

    this.apiDirName = "Endpoint";
    setApiPackage(getInvokerPackage() + "\\" + apiDirName);
  }

  @Override
  public void processOpts() {
    super.processOpts();

    this.clientsMode = additionalProperties.containsKey(CLIENTS_MODE);

    String clientClass = (String) additionalProperties.getOrDefault(CLIENT_CLASS, "Client");
    additionalProperties.put(CLIENT_CLASS, clientClass);

    if (clientClass.startsWith("Abstract")) {
      additionalProperties.put(CLIENT_CLASS_QUALIFIER, "abstract");
    }

    this.resetTemplateFiles();

    // PhpClientCodegen defaults to a "lib" source folder, but the generated
    // rector.php/phpcs.xml/composer.json all assume Model and Endpoint live
    // at the project root.
    this.setSrcBasePath("");

    if (this.clientsMode) {
        // Generated once per spec: one accessor + one createXxxClient()/createXxxMock()
        // hook pair per tag, dispatching to whatever client.mustache/mock.mustache
        // produced for that tag (see apiFilename()/addOperationToGroup() below).
        supportingFiles.add(new SupportingFile("clientBuilder.mustache", "Client", "AbstractClientBuilder.php"));
        // rector.php targets the whole Client/ directory, but is only written
        // during this pass since that's when clientsMode's supportingFiles run.
        supportingFiles.add(new SupportingFile("rector.mustache", "", "rector.php"));
    } else {
        supportingFiles.add(new SupportingFile("readme.mustache", "", "README.md"));
        supportingFiles.add(new SupportingFile("phpstan.mustache", "", "phpstan.neon"));
        supportingFiles.add(new SupportingFile("phpcs.mustache", "", "phpcs.xml"));
        supportingFiles.add(new SupportingFile("composer.mustache", "", "composer.json"));
        supportingFiles.add(new SupportingFile("phpmd.mustache", "", "phpmd.xml"));
        supportingFiles.add(new SupportingFile("grumphp.mustache", "", "grumphp.yml"));
        supportingFiles.add(new SupportingFile("gitignore.mustache", "", ".gitignore"));
    }

    if (additionalProperties.containsKey("factories")) {
        this.setSrcBasePath("Factory");
        modelTemplateFiles = new HashMap<>();
        modelTemplateFiles.put("factory.mustache", ".php");
        this.modelPackage = this.invokerPackage + "\\";
        this.modelNameSuffix = "Factory";
        this.fileSuffix = "Factory";
    }

    if (additionalProperties.containsKey("defaults")) {
        this.setSrcBasePath("Default");
        modelTemplateFiles = new HashMap<>();
        modelTemplateFiles.put("default.mustache", ".php");
        this.modelPackage = this.invokerPackage + "\\";
        this.modelNameSuffix = "Default";
        this.fileSuffix = "Default";
    }

    this.onlyJsonMediaTypes();
    this.onlyJsonComponents();
  }

  private void onlyJsonMediaTypes() {
    for (String pathName : openAPI.getPaths().keySet()) {
      PathItem pathItem = openAPI.getPaths().get(pathName);
      Map<HttpMethod, Operation> operations = pathItem.readOperationsMap();
      for (HttpMethod method : operations.keySet()) {
        Operation operation = operations.get(method);
        if (operation.getRequestBody() != null) {
          RequestBody requestBody = operation.getRequestBody();
          Content content = requestBody.getContent();
          if (content != null) {
            // remove all the media types except application/json
            MediaType mediaType = content.get("application/json");
              MediaType mediaTypePatch = content.get("application/merge-patch+json");
              if (mediaType != null || mediaTypePatch != null) {
                content.clear();
              }

                if (mediaType != null) {
                    content.addMediaType("application/json", mediaType);
                }

                if (mediaTypePatch != null) {
                    content.addMediaType("application/merge-patch+json", mediaTypePatch);
                }
          }
        }

        if (operation.getResponses() != null) {
          operation.getResponses().forEach((responseCode, response) -> {
            Content content = response.getContent();
            if (content != null) {
              // remove all the media types except application/json
              MediaType mediaType = content.get("application/json");
              MediaType mediaTypePatch = content.get("application/merge-patch+json");
              if (mediaType != null || mediaTypePatch != null) {
                content.clear();
              }

                if (mediaType != null) {
                    content.addMediaType("application/json", mediaType);
                }

                if (mediaTypePatch != null) {
                    content.addMediaType("application/merge-patch+json", mediaTypePatch);
                }
            }
          });
         }
      }
    }
  }

  private void onlyJsonComponents() {
    Components components = openAPI.getComponents();
    Map<String, Schema> schemas = components.getSchemas();
    schemas.keySet().removeIf(schemaName -> schemaName.contains(".jsonld") || schemaName.contains(".jsonhal"));
  }

  @Override
  public CodegenType getTag() {
    return CodegenType.CLIENT;
  }

  @Override
  public String getName() {
    return OpenApiGeneratorPhpClient.GENERATOR_NAME;
  }

  @Override
  public String toApiName(String name) {
    return org.openapitools.codegen.utils.StringUtils.camelize(name);
  }

  @Override
  public String toAnyOfName(List<String> names, Schema composedSchema) {
    return this.toComposedName(names, composedSchema);
  }

  @Override
  public String toOneOfName(List<String> names, Schema composedSchema) {
    return this.toComposedName(names, composedSchema);
  }

  @Override
  public String toAllOfName(List<String> names, Schema composedSchema) {
    return this.toComposedName(names, composedSchema);
  }

  private String toComposedName(List<String> names, Schema composedSchema) {
    if (ModelUtils.isStringSchema(composedSchema)) {
      return "string";
    }

    if (names.size() == 1) {
      String name = names.get(0);
      return this.toModelName(name);
    }

    return "mixed";
  }

  @Override
  public void addOperationToGroup(String tag, String resourcePath, Operation operation, CodegenOperation co, Map<String, List<CodegenOperation>> operations) {
      String uniqueName = co.operationId;
      co.operationIdLowerCase = uniqueName.toLowerCase(Locale.ROOT);
      co.operationIdCamelCase = org.openapitools.codegen.utils.StringUtils.camelize(uniqueName);
      co.operationIdSnakeCase = org.openapitools.codegen.utils.StringUtils.underscore(uniqueName);

      if (co.vendorExtensions == null) {
        co.vendorExtensions = new HashMap<>();
      }

      co.vendorExtensions.put("x-operation-scope", co.vendorExtensions.getOrDefault("x-operation-scope", "public"));
      co.vendorExtensions.put("x-add-to-documentation", co.vendorExtensions.getOrDefault("x-operation-scope", "public").equals("public"));

      if (this.clientsMode) {
          // Real tag-based grouping (framework already fans a multi-tag operation out
          // to one call per tag), so each generated {Tag}Client only sees its own ops.
          operations.computeIfAbsent(tag, key -> new ArrayList<>()).add(co);
          return;
      }

      // Endpoint generation must stay one file per operation (see api.mustache), so
      // this pass keeps grouping by operationId regardless of the operation's tags.
      operations.put(uniqueName, Arrays.asList(co));
  }

  @Override
  public String apiFilename(String templateName, String tag) {
      if (!this.clientsMode) {
          return super.apiFilename(templateName, tag);
      }

      String clientClass = (String) additionalProperties.getOrDefault(CLIENT_CLASS, "Client");
      String pascalTag = toApiName(tag);
      String filename;

      switch (templateName) {
          case "client.mustache":
              filename = pascalTag.concat(clientClass).concat(".php");
              break;
          case "clientInterface.mustache":
              filename = pascalTag.concat(clientClass).concat("Interface.php");
              break;
          case "mock.mustache":
              filename = "Api".concat(pascalTag).concat(clientClass).concat("Mock.php");
              break;
          default:
              return super.apiFilename(templateName, tag);
      }

      return this.outputFolder() + File.separator + "Client" + File.separator + filename;
  }

  @Override
  @SuppressWarnings("rawtypes")
  public String getTypeDeclaration(Schema p) {
    if (ModelUtils.isArraySchema(p)
        || ModelUtils.isMapSchema(p)
        || ModelUtils.isObjectSchema(p)
        || ModelUtils.isModel(p)
        || StringUtils.isNotBlank(p.get$ref())
    ) {
      return "array";
    }

    return super.getTypeDeclaration(p);
  }

  @Override
  public String getTypeDeclaration(String name) {
    if (!languageSpecificPrimitives.contains(name)) {
      return "array";
    }

    return super.getTypeDeclaration(name);
  }

  @Override
  public void preprocessOpenAPI(OpenAPI openAPI) {
    super.preprocessOpenAPI(openAPI);

    Paths paths = openAPI.getPaths();
    Components components = openAPI.getComponents();
    Map<String, Schema> schemas = components.getSchemas();

    for (String pathName : paths.keySet()) {
        PathItem pathItem = paths.get(pathName);
        Map<HttpMethod, Operation>  operations = pathItem.readOperationsMap();
        for(HttpMethod method: operations.keySet()) {
            Operation operation = operations.get(method);

            if (operation.getOperationId() == null) {
                operation.setOperationId(getOrGenerateOperationId(operation, pathName, method.toString().toLowerCase()));
            }

            if (operation.getParameters() != null) {
                this.createSchemaFromParameters(operation, components, "query");
            }

            if (operation.getRequestBody() != null) {
                this.createSchemaFromRequestBody(operation, components);
            }

            if (operation.getResponses() != null) {
                this.sortResponses(operation);
            }
        }
    }
  }

  @Override
  public List<CodegenParameter> fromRequestBodyToFormParameters(RequestBody body, Set<String> imports) {
        List<CodegenParameter> parameters = new ArrayList<CodegenParameter>();

        CodegenParameter parameter = CodegenModelFactory.newInstance(CodegenModelType.PARAMETER);
        parameter.baseType = body.getDescription();

        if (body.getRequired() != null) {
            parameter.required = body.getRequired();
        }

        parameters.add(parameter);

        return parameters;
  }

  @Override
  public String toApiImport(String name) {
      return apiPackage() + "\\" + name;
  }

  @Override
  public String toModelImport(String name) {
      if ("".equals(modelPackage())) {
          return name;
      } else {
          return modelPackage() + "\\" + name;
      }
  }

  @Override
  public String toModelName(final String name) {
      if (name.equals("mixed")) {
        return name;
      }

      if (name.equals("Object")) {
        return "\\stdClass";
      }

      return super.toModelName(name);
  }

  // Non-object schemas (enum, pattern, numeric range, recognized string format, or
  // explicitly opted in via x-value-object) are rendered by model.mustache as a
  // team-blue/php-value-objects subclass instead of the usual JsonSchemaAwareRecord.
  // DefaultCodegen would otherwise silently skip generating a file for most of these
  // (isAlias=true), so eligible models are un-aliased here to force file generation.
  @Override
  public CodegenModel fromModel(String name, Schema schema) {
    CodegenModel model = super.fromModel(name, schema);

    if (model.vendorExtensions == null) {
      model.vendorExtensions = new HashMap<>();
    }

    if (!isValueObjectModel(model)) {
      return model;
    }

    model.isAlias = false;
    model.vendorExtensions.put("x-is-value-object", true);

    String baseClass = resolveValueObjectBaseClass(model);
    model.vendorExtensions.put("x-vo-base-class", baseClass);
    model.vendorExtensions.put("x-vo-base-class-short", baseClass.substring(baseClass.lastIndexOf('\\') + 1));

    if (!model.isEnum) {
      applyValueObjectOverrides(model, baseClass);
    }

    return model;
  }

  // NOTE: pattern/minimum/maximum/exclusiveMinimum/exclusiveMaximum/format come from
  // the IJsonSchemaValidationProperties interface CodegenModel implements and are
  // only exposed via getters (no matching public fields) - confirmed against the
  // openapi-generator-7.21.0 javadoc. isString/isInteger/isNumber/isBoolean/isEnum
  // remain plain public fields as elsewhere in this class.
  private boolean isValueObjectModel(CodegenModel model) {
    if (model.isEnum) {
      return true;
    }

    // Recognized formats (uuid, email, ...) get their own upstream code path that
    // skips updateModelForString(), so isString is NOT set for them - checked
    // independently of the isString/isInteger/... gate below instead of relying on it.
    if (model.getFormat() != null && STRING_FORMAT_VALUE_OBJECTS.containsKey(model.getFormat())) {
      return true;
    }

    boolean hasConstraint = model.getPattern() != null || model.getMinimum() != null || model.getMaximum() != null;
    boolean explicitlyRequested = Boolean.TRUE.equals(model.vendorExtensions.get("x-value-object"));

    return (hasConstraint || explicitlyRequested)
        && (model.isString || model.isInteger || model.isNumber || model.isBoolean);
  }

  private String resolveValueObjectBaseClass(CodegenModel model) {
    if (model.isEnum) {
      return model.isString
          ? "TeamBlue\\ValueObjects\\Implementation\\Enum\\StringEnumValue"
          : "TeamBlue\\ValueObjects\\Implementation\\Enum\\IntEnumValue";
    }

    if (model.getFormat() != null && STRING_FORMAT_VALUE_OBJECTS.containsKey(model.getFormat())) {
      return STRING_FORMAT_VALUE_OBJECTS.get(model.getFormat());
    }

    if (model.getPattern() != null) {
      return "TeamBlue\\ValueObjects\\Implementation\\String\\PatternValue";
    }

    if (model.getMinimum() != null || model.getMaximum() != null) {
      return model.isInteger
          ? "TeamBlue\\ValueObjects\\Implementation\\Int\\RangeValue"
          : "TeamBlue\\ValueObjects\\Implementation\\Float\\FloatRangeValue";
    }

    if (model.isString) {
      return "TeamBlue\\ValueObjects\\Implementation\\String\\StringValue";
    }

    if (model.isInteger) {
      return "TeamBlue\\ValueObjects\\Implementation\\Int\\IntValue";
    }

    if (model.isBoolean) {
      return "TeamBlue\\ValueObjects\\Implementation\\Bool\\BoolValue";
    }

    return "TeamBlue\\ValueObjects\\Implementation\\Float\\FloatValue";
  }

  private void applyValueObjectOverrides(CodegenModel model, String baseClass) {
    boolean isPatternClass = baseClass.equals("TeamBlue\\ValueObjects\\Implementation\\String\\PatternValue");
    if (isPatternClass && model.getPattern() != null) {
      model.vendorExtensions.put("x-vo-needs-pattern-override", true);
      model.vendorExtensions.put("x-vo-pattern", model.getPattern().replace("\\", "\\\\").replace("'", "\\'"));
    }

    boolean isRangeClass = baseClass.equals("TeamBlue\\ValueObjects\\Implementation\\Int\\RangeValue")
        || baseClass.equals("TeamBlue\\ValueObjects\\Implementation\\Float\\FloatRangeValue");
    if (isRangeClass) {
      model.vendorExtensions.put("x-vo-needs-range-override", true);
      model.vendorExtensions.put("x-vo-minimum", model.getMinimum());
      model.vendorExtensions.put("x-vo-maximum", model.getMaximum());
      model.vendorExtensions.put("x-vo-included", !(model.getExclusiveMinimum() || model.getExclusiveMaximum()));
    }
  }

  @Override
  public CodegenProperty fromProperty(String name, Schema p) {
      CodegenProperty property = super.fromProperty(name, p);

      if (property.openApiType.equals("mixed")) {
        property.complexType = "mixed";
      }

      if (p != null && p.getExtensions() != null) {
          Object voType = p.getExtensions().get("x-vo-type");
          if (voType != null) {
              if (property.vendorExtensions == null) {
                  property.vendorExtensions = new HashMap<>();
              }
              property.vendorExtensions.put("x-has-vo-type", true);
          }
      }

      return property;
  }

  @Override
  public OperationsMap postProcessOperationsWithModels(OperationsMap objs, List<ModelMap> allModels) {
    objs = super.postProcessOperationsWithModels(objs, allModels);

    // Build a lookup of operationId -> parameter name -> schema so we can read
    // the x-vo-type extension from the original parameter schemas.
    Map<String, Map<String, Schema>> parameterSchemasByOperationId = new HashMap<>();
    for (PathItem pathItem : openAPI.getPaths().values()) {
        for (Operation operation : pathItem.readOperationsMap().values()) {
            if (operation.getOperationId() == null || operation.getParameters() == null) {
                continue;
            }
            Map<String, Schema> parameterSchemas = new HashMap<>();
            for (Parameter parameter : operation.getParameters()) {
                if (parameter.getName() != null && parameter.getSchema() != null) {
                    parameterSchemas.put(parameter.getName(), parameter.getSchema());
                }
            }
            parameterSchemasByOperationId.put(operation.getOperationId(), parameterSchemas);
        }
    }

    for (CodegenOperation op : objs.getOperations().getOperation()) {
        Map<String, Schema> parameterSchemas = parameterSchemasByOperationId.getOrDefault(op.operationId, Collections.emptyMap());
        List<List<CodegenParameter>> paramLists = Arrays.asList(
            op.allParams,
            op.pathParams,
            op.queryParams,
            op.headerParams,
            op.formParams,
            op.cookieParams
        );
        for (List<CodegenParameter> paramList : paramLists) {
            if (paramList == null) {
                continue;
            }
            for (CodegenParameter param : paramList) {
                this.applyVoTypeToParameter(param, parameterSchemas);
            }
        }
    }

    return objs;
  }

  private void applyVoTypeToParameter(CodegenParameter param, Map<String, Schema> parameterSchemas) {
    Object voType = param.vendorExtensions != null ? param.vendorExtensions.get("x-vo-type") : null;

    if (voType == null) {
        Schema parameterSchema = parameterSchemas.get(param.baseName);
        if (parameterSchema != null && parameterSchema.getExtensions() != null) {
            voType = parameterSchema.getExtensions().get("x-vo-type");
        }
    }

    if (voType == null) {
        return;
    }

    if (param.vendorExtensions == null) {
        param.vendorExtensions = new HashMap<>();
    }
    param.vendorExtensions.put("x-has-vo-type", true);
    param.vendorExtensions.put("x-vo-type", voType.toString());
  }

  private void createSchemaFromParameters(Operation operation, Components components, String inType) {
    List<Parameter> typeParameters = operation.getParameters()
        .stream()
        .filter(parameter -> (inType == "query" && parameter instanceof QueryParameter)
            || parameter.getIn() == inType
        )
        .collect(Collectors.toList());

        if (! typeParameters.isEmpty()) {
            String typeName = operation.getOperationId() + org.openapitools.codegen.utils.StringUtils.camelize(inType);

            Map<String, Schema> typeSchemas = typeParameters
                .stream()
                .collect(Collectors.toMap(Parameter::getName, Parameter::getSchema));

            List<String> requiredParameters = typeParameters
                .stream()
                .filter(parameter -> !(parameter.getRequired() == null || parameter.getRequired() == false))
                .map(parameter -> parameter.getName())
                .collect(Collectors.toList());

            ObjectSchema typeSchema = new ObjectSchema();
            typeSchema.setProperties(typeSchemas);
            typeSchema.setRequired(requiredParameters);

            components.addSchemas(typeName, typeSchema);
        }
  }

  private void createSchemaFromRequestBody(Operation operation, Components components) {
    if (operation.getRequestBody() == null) {
        return;
    }

    if (operation.getRequestBody().get$ref() != null) {
        this.createSchemaFromRefRequestBody(operation, components);

        return;
    }

    this.createSchemaFromFormData(operation, components);
  }

  private void createSchemaFromRefRequestBody(Operation operation, Components components) {
    Map<String, Schema> schemas = components.getSchemas();
    Map<String, RequestBody> requestBodies = components.getRequestBodies();
    RequestBody requestBody = operation.getRequestBody();

    String shortRef = ModelUtils.getSimpleRef(requestBody.get$ref());
    String newShortRef = operation.getOperationId() + "Body";

    // change the name in schemas
    Schema schema = schemas.get(shortRef);
    schemas.remove(shortRef);
    components.addSchemas(newShortRef, schema);

    // change the name in the requestBodies
    RequestBody componentRequestBody = requestBodies.get(shortRef);
    requestBodies.remove(shortRef);
    components.addRequestBodies(newShortRef, componentRequestBody);

    ModelUtils.getSchemaFromRequestBody(componentRequestBody).set$ref(newShortRef);
    requestBody.set$ref(newShortRef);
  }

  private void createSchemaFromFormData(Operation operation, Components components) {
    RequestBody requestBody = operation.getRequestBody();
    Schema schema = ModelUtils.getSchemaFromRequestBody(requestBody);
    String ref = operation.getOperationId() + "Body";

    // add new schema
    components.addSchemas(ref, schema);
    components.addRequestBodies(ref, requestBody);
    requestBody.set$ref(ref);
    requestBody.setDescription(org.openapitools.codegen.utils.StringUtils.camelize(ref));
  }

  private void sortResponses(Operation operation) {
    ApiResponses responses = operation.getResponses();
    Map<String, ApiResponse> sortedMap = new TreeMap<String, ApiResponse>(responses);

    responses.clear();
    for(String key : sortedMap.keySet()){
        responses.addApiResponse(key, sortedMap.get(key));
    }
  }

  @Override
  public void postProcessFile(File file, String fileType) {
      return;
  }

  @Override
  public ModelsMap postProcessModels(ModelsMap objs) {
        // Remove "double" vars that will lead to the same php property in a model
        // For example number and number[] will lead to the same property $number
        // So keep the number[] var and remove the number var
        objs = super.postProcessModels(objs);

        List<ModelMap> models = objs.getModels();
        ModelMap modelMap = models.get(0);
        CodegenModel model = modelMap.getModel();

        // Derive x-has-vo-type from the x-vo-type vendor extension.
        // DefaultCodegen copies all schema extensions into vendorExtensions, so we
        // only need to translate them into the flags used by the templates.
        List<List<CodegenProperty>> varLists = Arrays.asList(
            model.vars,
            model.allVars,
            model.requiredVars,
            model.optionalVars,
            model.readOnlyVars,
            model.readWriteVars
        );
        for (List<CodegenProperty> varList : varLists) {
            if (varList == null) {
                continue;
            }
            for (CodegenProperty var : varList) {
                if (var.vendorExtensions == null) {
                    continue;
                }
                Object voType = var.vendorExtensions.get("x-vo-type");
                if (voType != null) {
                    var.vendorExtensions.put("x-has-vo-type", true);
                }
            }
        }

        // Simplify model var type
        // define list of model vars to remove
        List<CodegenProperty> varsToRemove = new ArrayList<CodegenProperty>();
        for (Iterator<CodegenProperty> it = model.vars.iterator(); it.hasNext();) {
            CodegenProperty var = it.next();
            for (Iterator<CodegenProperty> it2 = model.vars.iterator(); it2.hasNext();) {
                CodegenProperty var2 = it2.next();
                if (var.baseName.equals(var2.baseName.concat("[]"))) {
                    // Replace the array notation without the array.
                    // The client adds the array twice
                    var.baseName = var2.baseName;
                    varsToRemove.add(var2);
                    break;
                }
            }
        }

        model.vars.removeAll(varsToRemove);
        model.allVars.removeAll(varsToRemove);
        model.requiredVars.removeAll(varsToRemove);
        model.optionalVars.removeAll(varsToRemove);
        model.readOnlyVars.removeAll(varsToRemove);
        model.readWriteVars.removeAll(varsToRemove);
        model.parentVars.removeAll(varsToRemove);

        return objs;
  }

  // Default.php ("...Default" trait, default(): self stub) and Factory.php
  // (fromArray()/fromRecordData() via FactoryFromArray) don't apply to a value
  // object: there's no array data to hydrate a scalar/enum from. Strip value-object
  // models from the map only during those two dedicated passes; the plain Model/
  // pass (which has neither key set) must still emit Model/{Name}.php for them.
  @Override
  public Map<String, ModelsMap> postProcessAllModels(Map<String, ModelsMap> objs) {
    objs = super.postProcessAllModels(objs);

    if (!additionalProperties.containsKey("factories") && !additionalProperties.containsKey("defaults")) {
      return objs;
    }

    Map<String, ModelsMap> processedModels = objs;
    Map<String, ModelsMap> filtered = new HashMap<>(objs);
    filtered.keySet().removeIf(modelName -> {
      List<ModelMap> models = processedModels.get(modelName).getModels();
      if (models == null || models.isEmpty()) {
        return false;
      }

      CodegenModel model = models.get(0).getModel();

      return model != null
          && model.vendorExtensions != null
          && Boolean.TRUE.equals(model.vendorExtensions.get("x-is-value-object"));
    });

    return filtered;
  }

  private void resetTemplateFiles() {
    this.supportingFiles.clear();
    this.apiTemplateFiles.clear();
    this.apiTestTemplateFiles.clear();
    this.apiDocTemplateFiles.clear();
    this.modelTestTemplateFiles.clear();
    this.modelDocTemplateFiles.clear();

    if (this.clientsMode) {
        apiTemplateFiles.put("client.mustache", ".php");
        apiTemplateFiles.put("clientInterface.mustache", ".php");
        apiTemplateFiles.put("mock.mustache", ".php");
    } else {
        apiTemplateFiles.put("api.mustache", ".php");
    }
  }

  @Override
  public String toEnumDefaultValue(String value, String datatype) {
    return value;
  }

  @Override
  public String toEnumVarName(String value, String datatype) { return value; }

  @Override
  public boolean isDataTypeString(String dataType) {
    return "String".equalsIgnoreCase(dataType);
  }

  @Override
  public String toVarName(String name) {
    if (name.matches("^\\d.*")) {
      return "prefixNumber" + name;
    }

    if (name.matches("^@.*")) {
      name = "_at" + name;
    }

    return super.toVarName(name);
  }

  @Override
  public String toGetter(String name) {
    return getterAndSetterCapitalize(name);
  }

  @Override
  public String toSetter(String name) {
    return getterAndSetterCapitalize(name);
  }

  @Override
  public String toBooleanGetter(String name) {
    return getterAndSetterCapitalize(name);
  }

  @Override
  public String getterAndSetterCapitalize(String name) {
    if (name == null || name.length() == 0) {
        return name;
    }
    return toVarName(name);
  }

  @Override
  public String toDefaultValue(Schema schema) {
    if (schema.getDefault() == null) {
      return super.toDefaultValue(schema);
    }

    if (ModelUtils.isStringSchema(schema)) {
        // escape single quotes in schema.getDefault().toString()
        return schema.getDefault().toString().replace("'", "\\'");
    }

    if (! ModelUtils.isArraySchema(schema)) {
      return super.toDefaultValue(schema);
    }

    ArraySchema arraySchema = (ArraySchema) schema;
    ArrayNode arrayNode = (ArrayNode) arraySchema.getDefault();
    if (arraySchema.getItems() != null
         && arrayNode.isEmpty() == false
         && ModelUtils.isStringSchema(arraySchema.getItems())
    ) {
        return arraySchema.getDefault().toString().replace("[", "['").replace("]", "']").replace(", ", "', '");
    }

    return arraySchema.getDefault().toString();
  }

  // added because uploading file parameters give problems
  @Override
  public boolean isParameterNameUnique(CodegenParameter p, List<CodegenParameter> parameters) {
      if (p.paramName == null) {
          return true;
      }

      return super.isParameterNameUnique(p, parameters);
  }
}
